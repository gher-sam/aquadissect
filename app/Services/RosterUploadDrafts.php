<?php

namespace App\Services;

use App\Models\LearningClass;
use App\Models\RosterEvent;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class RosterUploadDrafts
{
    private function directory(): string
    {
        $path = storage_path('app/private/roster-drafts');
        File::ensureDirectoryExists($path, 0700);

        return $path;
    }

    public function create(array $file, User $admin): array
    {
        $this->cleanup();
        $id = bin2hex(random_bytes(24));
        $draft = [...$file, 'owner' => $admin->id, 'expires' => now()->addMinutes(30)->timestamp, 'revision' => 1];
        $this->save($id, $draft);

        return ['draftId' => $id, 'revision' => 1, 'expiresAt' => $draft['expires'], 'format' => $file['format'], 'sheets' => $file['sheets']];
    }

    public function access(string $id, User $admin, callable $operation): mixed
    {
        abort_unless(preg_match('/^[a-f0-9]{48}$/D', $id), 404);
        $path = $this->directory().'/'.$id;
        // Lock the draft itself: cancellation and repeated submission cannot race a commit.
        $handle = @fopen($path, 'r+');
        abort_unless($handle, 410, 'This upload has expired or was completed. Upload the file again.');
        try {
            flock($handle, LOCK_EX);
            rewind($handle);
            $encrypted = stream_get_contents($handle);
            abort_unless($encrypted !== '', 410, 'This upload was completed or cancelled.');
            $draft = json_decode(Crypt::decryptString($encrypted), true, 30, JSON_THROW_ON_ERROR);
            abort_unless($draft['owner'] === $admin->id, 404);
            if ($draft['expires'] <= now()->timestamp || ! is_file($path)) {
                ftruncate($handle, 0);
                abort(410, 'This upload has expired. Upload the file again.');
            }
            $result = $operation($draft);
            if ($result === null) {
                ftruncate($handle, 0);

                return ['cancelled' => true];
            }
            [$draft, $response] = $result;
            if ($draft === null) {
                ftruncate($handle, 0);
            } else {
                rewind($handle);
                ftruncate($handle, 0);
                fwrite($handle, Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR)));
                fflush($handle);
            }

            return $response;
        } finally {
            $empty = fstat($handle)['size'] === 0;
            flock($handle, LOCK_UN);
            fclose($handle);
            if ($empty) {
                @unlink($path);
            }
        }
    }

    public function map(array $draft, array $input): array
    {
        $this->revision($draft, $input);
        $sheet = $draft['sheets'][$input['sheet']] ?? null;
        $this->check($sheet !== null, 'Choose an available worksheet or record element.');
        $header = $sheet['hasHeader'] ? $input['headerRow'] : 1;
        $columns = $sheet['rows'][$header - 1] ?? [];
        $mapping = $input['mapping'];
        foreach (['firstName', 'lastName', 'identityNumber'] as $field) {
            $this->check(isset($mapping[$field]) && isset($columns[$mapping[$field]]), 'Map student number, first name and surname.');
        }
        $used = array_values(array_filter($mapping, fn ($column) => $column !== null));
        $this->check(count(array_unique($used)) === count($used), 'Use a different column for each field.');
        $rows = [];
        foreach (array_slice($sheet['rows'], $header) as $index => $source) {
            if (! array_filter($source, fn ($value) => trim((string) $value) !== '')) {
                continue;
            }
            $row = ['id' => $index + $header + 1];
            foreach (['identityNumber', 'firstName', 'lastName', 'email', 'program', 'yearLevel', 'homeSection'] as $field) {
                $row[$field] = isset($mapping[$field]) ? trim((string) ($source[$mapping[$field]] ?? '')) : '';
            }
            $row['emailSuggested'] = false;
            if ($row['email'] === '' && $row['identityNumber'] !== '') {
                $matches = User::query()->whereRaw('UPPER(identity_number) = ?', [mb_strtoupper($row['identityNumber'])])->get();
                if ($matches->count() === 1 && $matches->first()->role === User::ROLE_STUDENT && $matches->first()->email) {
                    $row['email'] = $matches->first()->email;
                    $row['emailSuggested'] = true;
                }
            }
            $rows[] = $row;
        }
        $this->check(count($rows) > 0 && count($rows) <= 1000, 'Choose a header with between one and 1,000 student rows below it.');
        $draft['rows'] = $rows;
        unset($draft['review']);
        $draft['revision']++;

        return [$draft, ['revision' => $draft['revision'], 'rows' => $rows]];
    }

    public function review(array $draft, array $input): array
    {
        $this->revision($draft, $input);
        $rows = collect($draft['rows'] ?? [])->keyBy('id');
        $batch = [];
        foreach ($input['selected'] as $selection) {
            $row = $rows->get($selection['id']);
            $this->check($row !== null, 'A selected row is no longer available. Map the file again.');
            $batch[] = [...$row, 'email' => $selection['email'], 'offeringId' => $selection['offeringId']];
        }
        $preview = app(StudentRoster::class)->previewBatch($batch);
        $draft['revision']++;
        $draft['review'] = ['batch' => $batch, 'fingerprint' => hash('sha256', json_encode($preview))];

        return [$draft, ['revision' => $draft['revision'], ...$preview]];
    }

    public function commit(array $draft, array $input, User $admin): array
    {
        $this->revision($draft, $input);
        $this->check(isset($draft['review']), 'Review the selected students first.');
        $response = app(DelayedCloneWrite::class)->transaction(function () use ($draft, $admin) {
            $batch = $draft['review']['batch'];
            $ids = collect($batch)->pluck('offeringId')->unique()->sort()->values();
            $courseIds = LearningClass::query()->whereIn('id', $ids)->pluck('course_id')->unique()->sort();
            foreach ($courseIds as $courseId) {
                DB::table('courses')->where('id', $courseId)->lockForUpdate()->first();
            }
            LearningClass::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            $roster = app(StudentRoster::class);
            $preview = $roster->previewBatch($batch, true);
            $this->check(hash('sha256', json_encode($preview)) === $draft['review']['fingerprint'], 'Accounts or sections changed since review. Review the selection again.');
            $this->check($preview['counts']['errors'] === 0, 'Correct or deselect rows with errors before importing.');
            $codes = [];
            foreach (collect($batch)->groupBy('offeringId') as $id => $rows) {
                $result = $roster->import($rows->values()->all(), LearningClass::findOrFail($id), $admin, $draft['format']);
                $codes = [...$codes, ...$result['activationCodes']];
            }
            RosterEvent::query()->create(['actor_id' => $admin->id, 'action' => 'bulk_roster_imported', 'details' => ['source' => $draft['format'], 'destinations' => $ids->all(), 'counts' => $preview['counts']]]);

            return [...$preview, 'activationCodes' => $codes];
        });

        return [null, $response];
    }

    public function cleanup(): int
    {
        $count = 0;
        foreach (File::files($this->directory()) as $file) {
            $handle = @fopen($file->getPathname(), 'r+');
            if (! $handle) {
                continue;
            }
            try {
                if (! flock($handle, LOCK_EX | LOCK_NB)) {
                    continue;
                }
                $encrypted = stream_get_contents($handle);
                $draft = $encrypted === '' ? [] : json_decode(Crypt::decryptString($encrypted), true);
                if (($draft['expires'] ?? 0) <= now()->timestamp) {
                    ftruncate($handle, 0);
                    $count++;
                }
            } finally {
                $empty = fstat($handle)['size'] === 0;
                fclose($handle);
                if ($empty) {
                    @unlink($file->getPathname());
                }
            }
        }

        return $count;
    }

    private function save(string $id, array $draft): void
    {
        $path = $this->directory().'/'.$id;
        file_put_contents($path, Crypt::encryptString(json_encode($draft, JSON_THROW_ON_ERROR)), LOCK_EX);
        @chmod($path, 0600);
    }

    private function revision(array $draft, array $input): void
    {
        $this->check($draft['revision'] === $input['revision'], 'This upload changed. Use its latest review or upload again.');
    }

    private function check(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['roster' => $message]);
        }
    }
}
