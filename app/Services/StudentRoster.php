<?php

namespace App\Services;

use App\Models\AccountActivationToken;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\RosterEvent;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    public function preview(array $rows, LearningClass $class): array
    {
        $seenEmails = [];
        $seenIds = [];

        return collect($rows)->map(function (array $source, int $index) use ($class, &$seenEmails, &$seenIds): array {
            $row = [
                'firstName' => trim((string) ($source['firstName'] ?? '')),
                'lastName' => trim((string) ($source['lastName'] ?? '')),
                'email' => Str::lower(trim((string) ($source['email'] ?? ''))),
                'identityNumber' => Str::upper(trim((string) ($source['identityNumber'] ?? ''))),
            ];
            $validation = Validator::make($row, [
                'firstName' => ['required', 'string', 'max:80'],
                'lastName' => ['required', 'string', 'max:80'],
                'email' => ['required', 'email', 'max:255'],
                'identityNumber' => ['required', 'string', 'max:80'],
            ]);
            $status = 'new';
            $message = 'New student account and section enrollment.';

            if ($validation->fails()) {
                $status = 'invalid';
                $message = $validation->errors()->first();
            } elseif (isset($seenEmails[$row['email']]) || isset($seenIds[$row['identityNumber']])) {
                $status = 'conflict';
                $message = 'This email or institutional ID appears more than once in the import.';
            } else {
                $seenEmails[$row['email']] = true;
                $seenIds[$row['identityNumber']] = true;
                [$status, $message] = $this->classify($row, $class);
            }

            return ['row' => $index + 2, ...$row, 'status' => $status, 'message' => $message];
        })->all();
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array{rows: array<int, array<string, mixed>>, activationCodes: array<int, array<string, string>>}
     */
    public function import(array $rows, LearningClass $class, User $admin): array
    {
        return app(DelayedCloneWrite::class)->transaction(function () use ($rows, $class, $admin): array {
            $preview = $this->preview($rows, $class);
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
                        'email' => $row['email'],
                        'identity_number' => $row['identityNumber'],
                        'role' => User::ROLE_STUDENT,
                        'status' => User::STATUS_PENDING,
                        'class_code' => $class->code,
                        'password' => Str::random(64),
                    ]);
                    $codes[] = [
                        'name' => trim($user->first_name.' '.$user->last_name),
                        'email' => $user->email,
                        'code' => $this->issueActivationCode($user, $admin),
                    ];
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
                    'details' => ['source' => count($rows) === 1 ? 'manual' : 'csv'],
                ]);
            }

            return ['rows' => $preview, 'activationCodes' => $codes];
        });
    }

    public function issueActivationCode(User $student, User $admin): string
    {
        if ($student->role !== User::ROLE_STUDENT || $student->status !== User::STATUS_PENDING) {
            throw ValidationException::withMessages(['student' => 'Only pending student accounts can receive activation codes.']);
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
    private function classify(array $row, LearningClass $class): array
    {
        $emailMatch = User::query()->whereRaw('LOWER(email) = ?', [$row['email']])->first();
        $idMatch = User::query()->whereRaw('UPPER(identity_number) = ?', [$row['identityNumber']])->first();
        if (! $emailMatch && ! $idMatch) {
            return ['new', 'New student account and section enrollment.'];
        }
        if (! $emailMatch || ! $idMatch || $emailMatch->id !== $idMatch->id || $emailMatch->role !== User::ROLE_STUDENT) {
            return ['conflict', 'The email or institutional ID belongs to another record; review manually.'];
        }
        if (Str::lower(trim($emailMatch->first_name)) !== Str::lower($row['firstName'])
            || Str::lower(trim($emailMatch->last_name)) !== Str::lower($row['lastName'])) {
            return ['conflict', 'The name differs from the existing account; review manually.'];
        }
        if ($emailMatch->classEnrollments()->where('class_id', $class->id)->where('status', 'active')->exists()) {
            return ['already_enrolled', 'This student is already enrolled in the section.'];
        }
        $otherSection = $emailMatch->classEnrollments()->where('status', 'active')
            ->whereHas('learningClass', fn ($query) => $query
                ->where('course_id', $class->course_id)
                ->where('academic_year', $class->academic_year)
                ->where('semester', $class->semester)
                ->where('id', '!=', $class->id))
            ->exists();
        if ($otherSection) {
            return ['conflict', 'The student is enrolled in another section of this course and semester.'];
        }

        return ['existing_student', 'Existing student account will be enrolled in this section.'];
    }

    /** @param array<string, mixed> $row */
    private function matchingStudent(array $row): ?User
    {
        return User::query()->whereRaw('LOWER(email) = ?', [$row['email']])
            ->whereRaw('UPPER(identity_number) = ?', [$row['identityNumber']])->first();
    }
}
