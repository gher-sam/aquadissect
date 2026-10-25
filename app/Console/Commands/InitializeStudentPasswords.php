<?php

namespace App\Console\Commands;

use App\Models\LearningClass;
use App\Models\User;
use App\Services\StudentPasswords;
use Illuminate\Console\Command;

class InitializeStudentPasswords extends Command
{
    protected $signature = 'students:initialize-passwords {--actor=} {--academic-year=} {--semester=} {--section=*} {--apply}';

    protected $description = 'Prepare initial student-ID passwords for selected pending roster accounts without resetting active students';

    public function handle(StudentPasswords $passwords): int
    {
        $actor = User::find($this->option('actor'));
        $sections = $this->option('section');
        if (! config('student-setup.initial_password_enabled') || ! $actor?->isActive() || $actor->role !== User::ROLE_ADMIN
            || ! $this->option('academic-year') || ! $this->option('semester') || ! $sections) {
            $this->error('Enable the policy and specify an active administrator, academic year, semester and sections.');

            return self::FAILURE;
        }
        $classes = LearningClass::where('status', 'active')->where('academic_year', $this->option('academic-year'))
            ->where('semester', $this->option('semester'))->whereIn('section_code', $sections)
            ->whereHas('course', fn ($q) => $q->where('code', 'MB-304L'))->pluck('id');
        $students = User::where('role', User::ROLE_STUDENT)->where('status', User::STATUS_PENDING)
            ->where('must_change_password', false)->whereNull('password_changed_at')->whereNotNull('identity_number')->where('identity_number', '<>', '')
            ->whereHas('classEnrollments', fn ($q) => $q->where('status', 'active')->whereIn('class_id', $classes))->orderBy('id')->get();
        $this->line('Eligible pending students: '.$students->count());
        if (! $this->option('apply')) {
            $this->info('Preview only. No credentials or accounts changed.');

            return self::SUCCESS;
        }
        $count = 0;
        foreach ($students as $student) {
            $classId = $student->classEnrollments()->where('status', 'active')->whereIn('class_id', $classes)->value('class_id');
            $count += (int) $passwords->initialize($student, $actor, (int) $classId);
        }
        $this->info('Initial passwords prepared with audit events: '.$count.'. Active accounts were not reset.');

        return self::SUCCESS;
    }
}
