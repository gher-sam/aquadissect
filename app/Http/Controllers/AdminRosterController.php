<?php

namespace App\Http\Controllers;

use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\RosterEvent;
use App\Models\User;
use App\Services\CourseProvisioner;
use App\Services\DelayedCloneWrite;
use App\Services\StudentRoster;
use App\Support\ClassPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminRosterController extends Controller
{
    public function index(Request $request, ClassPresenter $presenter, CourseProvisioner $courses): JsonResponse
    {
        $course = $courses->ensure();
        $classes = $presenter->for($request->user());
        $results = LessonResult::query()->whereIn('class_id', collect($classes)->pluck('id'))
            ->selectRaw('class_id, COUNT(*) AS attempts, SUM(score) AS earned, SUM(total) AS available')
            ->groupBy('class_id')->get()->keyBy('class_id');
        $sectionReports = collect($classes)->map(function (array $class) use ($results): array {
            $row = $results->get($class['id']);
            $available = (int) ($row?->available ?? 0);

            return [
                'classId' => $class['id'],
                'name' => $class['name'],
                'academicYear' => $class['academicYear'],
                'semester' => $class['semester'],
                'students' => $class['studentCount'],
                'attempts' => (int) ($row?->attempts ?? 0),
                'scorePercent' => $available ? round(100 * (int) $row->earned / $available, 1) : null,
            ];
        })->all();
        $overallEarned = $results->sum(fn ($row) => (int) $row->earned);
        $overallAvailable = $results->sum(fn ($row) => (int) $row->available);

        return response()->json([
            'course' => ['id' => $course->id, 'code' => $course->code, 'name' => $course->name],
            'classes' => $classes,
            'report' => [
                'sections' => $sectionReports,
                'attempts' => $results->sum(fn ($row) => (int) $row->attempts),
                'scorePercent' => $overallAvailable ? round(100 * $overallEarned / $overallAvailable, 1) : null,
                'legacyUnattributedAttempts' => LessonResult::query()->whereNull('class_id')->count(),
            ],
            'instructors' => User::query()->where('role', User::ROLE_INSTRUCTOR)->where('status', User::STATUS_ACTIVE)
                ->orderBy('last_name')->get()->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => trim($user->first_name.' '.$user->last_name),
                ])->all(),
            'recentEvents' => RosterEvent::query()->latest()->limit(20)->get()
                ->map(fn (RosterEvent $event) => [
                    'action' => $event->action,
                    'classId' => $event->class_id,
                    'userId' => $event->user_id,
                    'createdAt' => $event->created_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function storeOffering(Request $request, CourseProvisioner $courses, DelayedCloneWrite $writes): JsonResponse
    {
        $validated = $request->validate([
            'academicYear' => ['required', 'regex:/^\d{4}-\d{4}$/'],
            'semester' => ['required', Rule::in(['1', '2', 'summer'])],
            'sectionCode' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9][A-Za-z0-9-]*$/'],
        ]);
        [$start, $end] = array_map('intval', explode('-', $validated['academicYear']));
        if ($end !== $start + 1) {
            throw ValidationException::withMessages(['academicYear' => 'Use consecutive years, such as 2026-2027.']);
        }
        $course = $courses->ensure();
        $section = strtoupper($validated['sectionCode']);
        $existing = LearningClass::query()
            ->where('course_id', $course->id)
            ->where('academic_year', $validated['academicYear'])
            ->where('semester', $validated['semester'])
            ->where('section_code', $section)->first();
        if ($existing) {
            throw ValidationException::withMessages(['sectionCode' => 'This section already exists for that course and semester. Reuse it.']);
        }

        return $writes->transaction(function () use ($request, $course, $validated, $section): JsonResponse {
            $code = 'MB304L-'.str_replace('-', '', $validated['academicYear']).'-'.$validated['semester'].'-'.$section;
            $class = LearningClass::query()->create([
                'course_id' => $course->id,
                'code' => $code,
                'name' => "MB 304L · Section {$section}",
                'academic_year' => $validated['academicYear'],
                'semester' => $validated['semester'],
                'section_code' => $section,
                'status' => 'active',
            ]);
            RosterEvent::query()->create([
                'actor_id' => $request->user()->id,
                'class_id' => $class->id,
                'action' => 'offering_created',
                'details' => ['academicYear' => $class->academic_year, 'semester' => $class->semester, 'sectionCode' => $section],
            ]);

            return response()->json(['classId' => $class->id], 201);
        });
    }

    public function assignInstructor(Request $request, LearningClass $class, DelayedCloneWrite $writes): JsonResponse
    {
        $validated = $request->validate([
            'instructorId' => ['required', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_INSTRUCTOR)->where('status', User::STATUS_ACTIVE)],
        ]);
        abort_unless($class->status === 'active', 422, 'Archived sections cannot receive instructors.');

        return $writes->transaction(function () use ($request, $class, $validated): JsonResponse {
            $assignment = ClassInstructor::query()->firstOrCreate([
                'class_id' => $class->id,
                'user_id' => $validated['instructorId'],
            ]);
            if ($assignment->wasRecentlyCreated) {
                RosterEvent::query()->create([
                    'actor_id' => $request->user()->id,
                    'user_id' => $validated['instructorId'],
                    'class_id' => $class->id,
                    'action' => 'instructor_assigned',
                ]);
            }

            return response()->json(['assigned' => true]);
        });
    }

    public function setOfferingStatus(Request $request, LearningClass $class, DelayedCloneWrite $writes): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['active', 'archived'])]]);

        return $writes->transaction(function () use ($request, $class, $validated): JsonResponse {
            if ($class->status !== $validated['status']) {
                $class->update(['status' => $validated['status']]);
                RosterEvent::query()->create([
                    'actor_id' => $request->user()->id,
                    'class_id' => $class->id,
                    'action' => $validated['status'] === 'archived' ? 'offering_archived' : 'offering_restored',
                ]);
            }

            return response()->json(['status' => $class->status]);
        });
    }

    public function storeStudent(Request $request, StudentRoster $roster): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'integer', 'exists:classes,id'],
            'firstName' => ['required', 'string', 'max:80'],
            'lastName' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'identityNumber' => ['required', 'string', 'max:80'],
        ]);
        $class = LearningClass::query()->where('status', 'active')->findOrFail($validated['classId']);
        $result = $roster->import([$validated], $class, $request->user());

        return response()->json($result, 201);
    }

    public function preview(Request $request, StudentRoster $roster): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'integer', 'exists:classes,id'],
            'roster' => ['required', 'file', 'max:2048'],
        ]);
        $class = LearningClass::query()->where('status', 'active')->findOrFail($validated['classId']);

        return response()->json(['rows' => $roster->preview($roster->readCsv($validated['roster']), $class)]);
    }

    public function import(Request $request, StudentRoster $roster): JsonResponse
    {
        $validated = $request->validate([
            'classId' => ['required', 'integer', 'exists:classes,id'],
            'roster' => ['required', 'file', 'max:2048'],
        ]);
        $class = LearningClass::query()->where('status', 'active')->findOrFail($validated['classId']);

        return response()->json($roster->import($roster->readCsv($validated['roster']), $class, $request->user()));
    }

    public function issueActivation(Request $request, User $user, StudentRoster $roster, DelayedCloneWrite $writes): JsonResponse
    {
        return $writes->transaction(fn (): JsonResponse => response()->json([
            'email' => $user->email,
            'code' => $roster->issueActivationCode($user, $request->user()),
            'expiresAt' => now()->addDays(3)->toIso8601String(),
        ]));
    }
}
