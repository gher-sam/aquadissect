<?php

namespace App\Services;

use App\Models\AccountActivationToken;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\RosterEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentRoster
{
    /** @return array<int, array<string, string>> */
    public function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            throw ValidationException::withMessages(['roster' => 'The roster could not be read.']);
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            $header = array_map(fn ($value) => strtolower(trim((string) $value, " \t\n\r\0\x0B\xEF\xBB\xBF")), $header ?: []);
            if ($header !== ['first_name', 'last_name', 'email', 'identity_number']) {
                throw ValidationException::withMessages(['roster' => 'Use CSV columns: first_name,last_name,email,identity_number.']);
            }

            $rows = [];
            while (($fields = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($fields === [null]) {
                    continue;
                }
                if (count($fields) !== 4) {
                    throw ValidationException::withMessages(['roster' => 'Every roster row must contain four columns.']);
                }
                $rows[] = array_combine(['firstName', 'lastName', 'email', 'identityNumber'], $fields);
                if (count($rows) > 1000) {
                    throw ValidationException::withMessages(['roster' => 'A roster may contain at most 1,000 students.']);
                }
            }
            if ($rows === []) {
                throw ValidationException::withMessages(['roster' => 'The roster has no student rows.']);
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function preview(array $rows, LearningClass $class, bool $lock = false): array
    {
        $seenEmails = [];
        $seenIds = [];

        return collect($rows)->map(function (array $source, int $index) use ($class, $lock, &$seenEmails, &$seenIds): array {
            $row = [
                'firstName' => trim((string) ($source['firstName'] ?? '')),
                'lastName' => trim((string) ($source['lastName'] ?? '')),
                'email' => Str::lower(trim((string) ($source['email'] ?? ''))),
                'identityNumber' => Str::upper(trim((string) ($source['identityNumber'] ?? ''))),
            ];
            $validation = Validator::make($row, [
                'firstName' => ['required', 'string', 'max:80'],
                'lastName' => ['required', 'string', 'max:80'],
                'email' => ['nullable', 'email', 'max:255'],
                'identityNumber' => ['required', 'string', 'max:80'],
            ]);
            $status = 'new';
            $message = 'New student account and section enrollment.';

            if ($validation->fails()) {
                $status = 'invalid';
                $message = $validation->errors()->first();
            } elseif (($row['email'] !== '' && isset($seenEmails[$row['email']])) || isset($seenIds[$row['identityNumber']])) {
                $status = 'conflict';
                $message = 'This email or institutional ID appears more than once in the import.';
            } else {
                if ($row['email'] !== '') {
                    $seenEmails[$row['email']] = true;
                }
                $seenIds[$row['identityNumber']] = true;
                [$status, $message] = $this->classify($row, $class, $lock);
            }

            return ['row' => $index + 2, ...$row, 'status' => $status, 'message' => $message];
        })->all();
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, activationCodes: array<int, array<string, string>>}
     */
    public function import(array $rows, LearningClass $class, User $admin, ?string $source = null): array
    {
        return app(DelayedCloneWrite::class)->transaction(function () use ($rows, $class, $admin, $source): array {
            DB::table('courses')->where('id', $class->course_id)->lockForUpdate()->first();
            $class = LearningClass::query()->lockForUpdate()->findOrFail($class->id);
            if ($class->status !== 'active') {
                throw ValidationException::withMessages(['roster' => 'The destination section is no longer active.']);
            }
            $preview = $this->preview($rows, $class, true);
            if (collect($preview)->contains(fn ($row) => in_array($row['status'], ['invalid', 'conflict'], true))) {
                throw ValidationException::withMessages(['roster' => 'Resolve the invalid or conflicting rows before import.']);
            }

            $codes = [];
            foreach ($preview as $row) {
                if ($row['status'] === 'already_enrolled') {
                    continue;
                }
                $user = $this->matchingStudent($row);
                if (! $user) {
                    $user = User::query()->create([
                        'first_name' => $row['firstName'],
                        'last_name' => $row['lastName'],
                        'email' => $row['email'] !== '' ? $row['email'] : null,
                        'identity_number' => $row['identityNumber'],
                        'role' => User::ROLE_STUDENT,
                        'status' => User::STATUS_PENDING,
                        'class_code' => $class->code,
                        'password' => Str::random(64),
                    ]);
                    if ($user->email) {
                        $codes[] = [
                            'name' => trim($user->first_name.' '.$user->last_name),
                            'email' => $user->email,
                            'code' => $this->issueActivationCode($user, $admin),
                        ];
                    }
                    $action = 'student_created';
                } else {
                    $action = 'student_enrolled';
                }

                ClassEnrollment::query()->updateOrCreate(
                    ['class_id' => $class->id, 'user_id' => $user->id],
                    ['status' => 'active'],
                );
                RosterEvent::query()->create([
                    'actor_id' => $admin->id,
                    'user_id' => $user->id,
                    'class_id' => $class->id,
                    'action' => $action,
                    'details' => ['source' => $source ?? (count($rows) === 1 ? 'manual' : 'csv')],
                ]);
            }

            return ['rows' => $preview, 'activationCodes' => $codes];
        });
    }

    public function previewBatch(array $batch, bool $lock = false): array
    {
        $classes = LearningClass::query()->with('course')->whereIn('id', collect($batch)->pluck('offeringId'))->get()->keyBy('id');
        $seenIds = [];
        $seenEmails = [];
        $rows = [];
        $counts = ['selected' => count($batch), 'new' => 0, 'existing_student' => 0, 'already_enrolled' => 0, 'errors' => 0];
        $destinations = [];
        foreach ($batch as $source) {
            $class = $classes->get($source['offeringId']);
            if (! $class || $class->status !== 'active' || $class->course?->code !== 'MB-304L') {
                $row = [...$source, 'status' => 'invalid', 'message' => 'Choose an active MB 304L course section.'];
            } else {
                $row = [...$source, ...$this->preview([$source], $class, $lock)[0], 'row' => $source['id']];
                $id = mb_strtoupper(trim($row['identityNumber']));
                $email = mb_strtolower(trim($row['email']));
                if (isset($seenIds[$id]) || ($email !== '' && isset($seenEmails[$email]))) {
                    $row['status'] = 'conflict';
                    $row['message'] = 'Select each student only once per batch.';
                }
                $seenIds[$id] = true;
                if ($email !== '') {
                    $seenEmails[$email] = true;
                }
                $key = (string) $class->id;
                $destinations[$key] ??= ['id' => $class->id, 'name' => $class->name, 'academicYear' => $class->academic_year, 'semester' => $class->semester, 'count' => 0];
                $destinations[$key]['count']++;
            }
            $counts[in_array($row['status'], ['invalid', 'conflict']) ? 'errors' : $row['status']]++;
            $rows[] = $row;
        }

        return ['rows' => $rows, 'counts' => $counts, 'destinations' => array_values($destinations)];
    }

    public function issueActivationCode(User $student, User $admin): string
    {
        if ($student->role !== User::ROLE_STUDENT || $student->status !== User::STATUS_PENDING) {
            throw ValidationException::withMessages(['student' => 'Only pending student accounts can receive activation codes.']);
        }
        if (! $student->email) {
            throw ValidationException::withMessages(['student' => 'Add the student email before issuing an activation code.']);
        }
        $code = bin2hex(random_bytes(24));
        AccountActivationToken::query()->updateOrCreate(
            ['user_id' => $student->id],
            ['token_hash' => hash('sha256', $code), 'expires_at' => now()->addDays(3)],
        );
        RosterEvent::query()->create([
            'actor_id' => $admin->id,
            'user_id' => $student->id,
            'action' => 'activation_code_issued',
            'details' => ['expiresAt' => now()->addDays(3)->toIso8601String()],
        ]);

        return $code;
    }

    /** @param array<string, string> $row
     * @return array{string, string}
     */
    private function classify(array $row, LearningClass $class, bool $lock = false): array
    {
        $emailMatch = $row['email'] !== ''
            ? User::query()->whereRaw('LOWER(email) = ?', [$row['email']])->when($lock, fn ($query) => $query->lockForUpdate())->first()
            : null;
        $idMatch = User::query()->whereRaw('UPPER(identity_number) = ?', [$row['identityNumber']])->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if (! $emailMatch && ! $idMatch) {
            return ['new', $row['email'] === ''
                ? 'New pending account and enrollment. Email is needed before activation.'
                : 'New student account and section enrollment.'];
        }
        if (! $idMatch || $idMatch->role !== User::ROLE_STUDENT
            || ($row['email'] !== '' && (! $emailMatch || $emailMatch->id !== $idMatch->id))) {
            return ['conflict', 'The email or institutional ID belongs to another record; review manually.'];
        }
        $emailMatch = $idMatch;
        if (Str::lower(trim($emailMatch->first_name)) !== Str::lower($row['firstName'])
            || Str::lower(trim($emailMatch->last_name)) !== Str::lower($row['lastName'])) {
            return ['conflict', 'The name differs from the existing account; review manually.'];
        }
        if ($emailMatch->classEnrollments()->where('class_id', $class->id)->where('status', 'active')->when($lock, fn ($query) => $query->lockForUpdate())->first()) {
            return ['already_enrolled', 'This student is already enrolled in the section.'];
        }
        $otherSection = $emailMatch->classEnrollments()->where('status', 'active')
            ->whereHas('learningClass', fn ($query) => $query
                ->where('course_id', $class->course_id)
                ->where('academic_year', $class->academic_year)
                ->where('semester', $class->semester)
                ->where('id', '!=', $class->id))
            ->when($lock, fn ($query) => $query->lockForUpdate())->first();
        if ($otherSection) {
            return ['conflict', 'The student is enrolled in another section of this course and semester.'];
        }

        return ['existing_student', 'Existing student account will be enrolled in this section.'];
    }

    /** @param array<string, mixed> $row */
    private function matchingStudent(array $row): ?User
    {
        return User::query()->when($row['email'] !== '', fn ($query) => $query->whereRaw('LOWER(email) = ?', [$row['email']]))
            ->whereRaw('UPPER(identity_number) = ?', [$row['identityNumber']])->first();
    }
}
