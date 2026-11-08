<?php

namespace App\Services;

use App\Models\AdminRecordArchive;
use App\Models\AdminRecordBatch;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LearningModuleVersion;
use App\Models\RosterEvent;
use App\Models\SpeciesPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminRecordManager
{
    public const MODELS = [
        'students' => User::class,
        'enrollments' => ClassEnrollment::class,
        'sections' => LearningClass::class,
        'species-versions' => SpeciesPackage::class,
        'safety-versions' => LearningModuleVersion::class,
    ];

    private ?array $foreignKeys = null;

    public function query(string $resource): Builder
    {
        abort_unless(isset(self::MODELS[$resource]), 422, 'Choose a supported record type.');
        $query = self::MODELS[$resource]::query();

        return $resource === 'students' ? $query->where('role', User::ROLE_STUDENT) : $query;
    }

    public function label(string $resource, Model $record): string
    {
        return match ($resource) {
            'students' => trim($record->first_name.' '.$record->last_name).' · '.$record->identity_number,
            'enrollments' => trim($record->user->first_name.' '.$record->user->last_name).' · '.$record->learningClass->name,
            'sections' => $record->name.' · '.$record->academic_year.' · '.$record->semester,
            'species-versions' => $record->species->common_name.' · v'.$record->version,
            'safety-versions' => $record->module->title.' · v'.$record->version,
        };
    }

    public function isArchived(Model $record): bool
    {
        return in_array($record->status, ['archived', 'withdrawn'], true);
    }

    public function preview(Request $request, array $input): array
    {
        $ids = $input['ids'];
        sort($ids, SORT_NUMERIC);
        $records = $this->query($input['resource'])->whereKey($ids)->orderBy('id')->get();
        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages(['ids' => 'A selected record is no longer available in this record list. Refresh the list.']);
        }
        [$rows, $state] = $this->inspect($input['resource'], $input['action'], $records);
        $payload = [
            'actor' => $request->user()->id, 'session' => hash('sha256', $request->session()->getId()),
            'resource' => $input['resource'], 'action' => $input['action'], 'ids' => $ids,
            'reason' => $input['reason'], 'state' => $state, 'expires' => now()->addMinutes(5)->timestamp,
        ];

        return ['token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR)), 'rows' => $rows,
            'allowed' => collect($rows)->every('allowed'), 'expiresAt' => now()->addMinutes(5)->toIso8601String()];
    }

    public function execute(Request $request, array $input): array
    {
        try {
            $payload = json_decode(Crypt::decryptString($input['token']), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            abort(422, 'The review could not be verified. Review the selection again.');
        }
        abort_unless($payload['actor'] === $request->user()->id
            && hash_equals($payload['session'], hash('sha256', $request->session()->getId())), 403);
        abort_unless($input['confirmation'] === ($payload['action'] === 'delete' ? 'DELETE' : 'CONFIRM'), 422, 'Confirm the reviewed action.');
        $hash = hash('sha256', $input['token']);
        $existing = AdminRecordBatch::query()->where('request_uuid', $input['requestUuid'])->first();
        if ($existing) {
            abort_unless($existing->actor_id === $request->user()->id && hash_equals($existing->request_hash, $hash), 409, 'This request identifier was already used.');

            return $this->result($existing, true);
        }
        abort_if($payload['expires'] < now()->timestamp, 409, 'The review expired. Review the selection again.');

        try {
            return DB::transaction(function () use ($payload, $input, $request, $hash): array {
                $records = $this->query($payload['resource'])->whereKey($payload['ids'])->orderBy('id')->lockForUpdate()->get();
                abort_unless($records->count() === count($payload['ids']), 409, 'A selected record changed. Review the selection again.');
                [$rows, $state] = $this->inspect($payload['resource'], $payload['action'], $records, true);
                abort_unless(hash_equals($payload['state'], $state), 409, 'Records or linked history changed. Review the selection again.');
                abort_unless(collect($rows)->every('allowed'), 422, 'The selection contains protected records. No changes were made.');
                $batch = AdminRecordBatch::query()->create([
                    'request_uuid' => $input['requestUuid'], 'actor_id' => $request->user()->id,
                    'resource_type' => $payload['resource'], 'action' => $payload['action'], 'request_hash' => $hash,
                    'reason' => $payload['reason'], 'record_ids' => $payload['ids'], 'audit_details' => [],
                ]);
                $details = [];
                foreach ($records as $record) {
                    $details[] = $this->apply($payload['resource'], $payload['action'], $record, $request->user());
                }
                $batch->update(['audit_details' => $details]);

                return $this->result($batch, false);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $batch = AdminRecordBatch::query()->where('request_uuid', $input['requestUuid'])->first();
            if (! $batch || $batch->actor_id !== $request->user()->id || ! hash_equals($batch->request_hash, $hash)) {
                abort(409, 'The request changed or was already used. Refresh and review again.');
            }

            return $this->result($batch, true);
        }
    }

    private function result(AdminRecordBatch $batch, bool $replayed): array
    {
        return ['batchId' => $batch->id, 'action' => $batch->action, 'count' => count($batch->record_ids), 'replayed' => $replayed];
    }

    private function inspect(string $resource, string $action, $records, bool $lock = false): array
    {
        $rows = [];
        $state = [];
        foreach ($records as $record) {
            $references = $this->references($record, $lock);
            $archive = AdminRecordArchive::query()->where('resource_type', $resource)->where('record_id', $record->id)->first();
            $reason = match ($action) {
                'archive' => $this->isArchived($record) ? 'Already archived.' : null,
                'restore' => ! $this->isArchived($record) ? 'Only archived records can be restored.'
                    : ($resource === 'students' && ! $archive ? 'The previous account status is unknown; use individual account support.' : null),
                'delete' => $this->deletionBlocker($resource, $record, $references),
            };
            $rows[] = ['id' => $record->id, 'label' => $this->label($resource, $record), 'status' => $record->status,
                'allowed' => $reason === null, 'message' => $reason ?? match ($action) {
                    'delete' => 'Unused record. Its removal will be recorded in the audit history.',
                    'restore' => in_array($resource, ['species-versions', 'safety-versions'], true) ? 'Returns to draft or review; approval and publication are still required.' : 'Restores the previous record status.',
                    default => 'History is retained. Account or section access may stop; previously published safety work keeps its existing retention rules.',
                }];
            $state[] = [$record->getRawOriginal(), $archive?->getRawOriginal(), $references, $reason];
        }

        return [$rows, hash('sha256', json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))];
    }

    /** Include linked-row state so edits or new activity invalidate an earlier review. */
    private function references(Model $record, bool $lock): array
    {
        if ($this->foreignKeys === null) {
            $this->foreignKeys = [];
            $tables = Schema::getTableListing(schemaQualified: false);
            sort($tables);
            foreach ($tables as $table) {
                foreach (Schema::getForeignKeys($table) as $key) {
                    if (count($key['columns']) === 1 && $key['foreign_columns'] === ['id']) {
                        $this->foreignKeys[$key['foreign_table']][] = [$table, $key['columns'][0]];
                    }
                }
            }
        }
        $facts = [];
        foreach ($this->foreignKeys[$record->getTable()] ?? [] as [$table, $column]) {
            $query = DB::table($table)->where($column, $record->id);
            if ($lock) {
                $query->lockForUpdate();
            }
            $rows = $query->get()->map(fn ($row) => (array) $row)->all();
            usort($rows, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
            $facts[$table.'.'.$column] = ['count' => count($rows), 'hash' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
        }
        if ($record instanceof ClassEnrollment) {
            foreach (['lesson_results', 'learning_module_progress', 'learning_module_attempts', 'learning_module_overrides'] as $table) {
                $query = DB::table($table)->where('user_id', $record->user_id)
                    ->where(fn ($q) => $q->where('class_id', $record->class_id)->orWhereNull('class_id'));
                if ($lock) {
                    $query->lockForUpdate();
                }
                $history = $query->orderBy('id')->get();
                $facts[$table.'.learning_history'] = ['count' => $history->count(), 'hash' => hash('sha256', $history->toJson())];
            }
            $query = DB::table('assessment_attempts')->join('assessment_studies', 'assessment_studies.id', '=', 'assessment_attempts.assessment_study_id')
                ->where('assessment_attempts.user_id', $record->user_id)->where('assessment_studies.class_id', $record->class_id);
            if ($lock) {
                $query->lockForUpdate();
            }
            $history = $query->orderBy('assessment_attempts.id')->get();
            $facts['assessment_attempts.learning_history'] = ['count' => $history->count(), 'hash' => hash('sha256', $history->toJson())];
        }

        return $facts;
    }

    private function deletionBlocker(string $resource, Model $record, array $references): ?string
    {
        $status = $record->status;
        $original = AdminRecordArchive::query()->where('resource_type', $resource)->where('record_id', $record->id)->value('original_state');
        if (is_string($original)) {
            $original = json_decode($original, true);
        }
        $status = $this->isArchived($record) ? ($original['status'] ?? $status) : $status;
        if ($resource === 'students' && $status !== User::STATUS_PENDING) {
            return 'Activated student accounts are retained. Archive this account instead.';
        }
        if (in_array($resource, ['species-versions', 'safety-versions'], true)
            && ($status !== 'draft' || $record->published_at || ($record->review_round ?? 0) > 0)) {
            return 'Published or reviewed content is retained. Archive this version instead.';
        }
        foreach ($references as $key => $reference) {
            if (! $reference['count']) {
                continue;
            }
            if ($this->ownedMetadata($resource, $record, $key)) {
                continue;
            }

            return 'Linked accounts, enrollments, activity, approvals or audit history must be retained. Archive this record instead.';
        }

        return null;
    }

    private function ownedMetadata(string $resource, Model $record, string $key): bool
    {
        return match ($resource) {
            'species-versions' => $key === 'species_package_assets.species_package_id'
                || ($key === 'species_package_events.species_package_id' && ! $record->events()->where('event', '!=', 'draft_imported')->exists()),
            'safety-versions' => $key === 'learning_module_assets.learning_module_version_id',
            'sections' => $key === 'roster_events.class_id' && ! RosterEvent::where('class_id', $record->id)->where('action', '!=', 'offering_created')->exists(),
            'students' => $key === 'roster_events.user_id' && ! RosterEvent::where('user_id', $record->id)->where('action', '!=', 'student_created')->exists(),
            default => false,
        };
    }

    private function apply(string $resource, string $action, Model $record, User $actor): array
    {
        $before = $record->only(['id', 'status', 'version', 'is_current', 'published_at', 'archived_at',
            'user_id', 'class_id', 'course_id', 'species_id', 'learning_module_id']);
        if ($record instanceof LearningModuleVersion) {
            $before['publishedHistory'] = $record->hasPublishedHistory();
        }
        $audit = ['recordId' => $record->id, 'before' => $before];
        if ($action === 'delete') {
            $audit['retainedSetupEvents'] = [];
            if ($record instanceof SpeciesPackage) {
                $audit['retainedSetupEvents'] = $record->events()->get()->map->getAttributes()->all();
                $record->events()->get()->each->delete();
                $record->assets()->get()->each->delete();
            } elseif ($record instanceof LearningModuleVersion) {
                $record->assets()->get()->each->delete();
            } elseif ($record instanceof LearningClass || $record instanceof User) {
                $column = $record instanceof User ? 'user_id' : 'class_id';
                $events = RosterEvent::where($column, $record->id)->get();
                $audit['retainedSetupEvents'] = $events->map->getAttributes()->all();
                $events->each->delete();
            }
            // Uploaded files remain private for recovery. Deleted version numbers are not reused.
            $record->delete();

            return $audit;
        }
        if ($action === 'archive') {
            AdminRecordArchive::query()->updateOrCreate(['resource_type' => $resource, 'record_id' => $record->id], [
                'original_state' => $before, 'actor_id' => $actor->id, 'archived_at' => now(), 'restored_at' => null,
                'review_cutoff' => $record instanceof LearningModuleVersion ? (int) $record->reviews()->max('id') : 0,
            ]);
            $update = ['status' => $record instanceof LearningModuleVersion && ! $record->hasPublishedHistory() ? 'withdrawn' : 'archived'];
            if ($record instanceof SpeciesPackage || $record instanceof LearningModuleVersion) {
                $update += ['is_current' => false, 'archived_at' => now()];
            }
            if ($record instanceof User) {
                $update['password_revision'] = $record->password_revision + 1;
            }
            $record->forceFill($update)->save();
        } else {
            $archive = AdminRecordArchive::query()->where('resource_type', $resource)->where('record_id', $record->id)->first();
            $update = ['status' => $archive?->original_state['status'] ?? 'active'];
            if ($record instanceof SpeciesPackage) {
                $update = ['status' => ($archive?->original_state['status'] ?? null) === 'draft' ? 'draft' : 'review',
                    'is_current' => false, 'archived_at' => null, 'reviewed_by' => null, 'review_round' => $record->review_round + 1];
            } elseif ($record instanceof LearningModuleVersion) {
                $update = ['status' => $record->hasPublishedHistory() || $record->status === 'archived' ? 'review' : 'draft', 'is_current' => false, 'archived_at' => null];
                if (! $archive) {
                    $archive = AdminRecordArchive::query()->create(['resource_type' => $resource, 'record_id' => $record->id,
                        'actor_id' => $actor->id, 'original_state' => $before, 'archived_at' => $record->archived_at ?? now(),
                        'review_cutoff' => (int) $record->reviews()->max('id')]);
                } else {
                    $archive->update(['review_cutoff' => max($archive->review_cutoff, (int) $record->reviews()->max('id'))]);
                }
            }
            $record->forceFill($update)->save();
            $archive?->update(['restored_at' => now()]);
        }
        if ($record instanceof SpeciesPackage) {
            $record->events()->create(['user_id' => $actor->id, 'event' => $action === 'archive' ? 'archived' : 'restored_for_review', 'metadata' => ['source' => 'admin_bulk_action']]);
        }
        $audit['after'] = $record->fresh()->only(['id', 'status', 'is_current']);

        return $audit;
    }
}
