<?php

namespace App\Services\Reports;

use App\Models\AssessmentStudy;
use App\Models\LearningModuleOverride;
use App\Models\LearningModuleProgress;
use App\Models\LessonResult;
use App\Models\User;
use App\Support\WorkspaceAccess;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningReport
{
    public function __construct(private readonly CompletionForecast $forecaster) {}

    public function build(User $user, array $input): array
    {
        $now = now('Asia/Manila');
        $filters = array_filter($input, fn ($v) => $v !== null && $v !== '');
        $filters += ['to' => $now->format('Y-m-d'), 'activity' => 'all', 'offeringStatus' => 'active', 'horizon' => 14];
        $filters += ['from' => Carbon::parse($filters['to'], 'Asia/Manila')->subDays(29)->format('Y-m-d')];
        if ($filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['from' => 'The start date must be on or before the end date.']);
        }
        if ($user->role !== 'student' && $filters['activity'] === 'practice') {
            abort(403, 'Practice rewards are private to the learner.');
        }
        if ($user->role === 'student' && isset($filters['learnerId']) && (int) $filters['learnerId'] !== $user->id) {
            abort(403);
        }

        $available = WorkspaceAccess::classes($user)->orderByDesc('academic_year')->orderBy('name')->get();
        if (isset($filters['classId']) && ! $available->contains('id', (int) $filters['classId'])) {
            abort(403);
        }
        $classes = $available->filter(fn ($c) => (! isset($filters['classId']) || $c->id === (int) $filters['classId'])
            && (! isset($filters['academicYear']) || $c->academic_year === $filters['academicYear'])
            && (! isset($filters['semester']) || $c->semester === $filters['semester'])
            && ($filters['offeringStatus'] === 'all' || $c->status === $filters['offeringStatus']))->values();
        $classes->load(['enrollments.user', 'assignments.package.species', 'moduleAssignments.version.module']);
        $ids = $classes->pluck('id')->all();
        $historical = ! isset($filters['classId']) && ! isset($filters['academicYear']) && ! isset($filters['semester'])
            && in_array($user->role, ['admin', 'student'], true);
        $lessons = $this->scope(LessonResult::with(['user', 'learningClass', 'package.species']), $ids, $user, $historical)->get();
        $safety = $this->scope(LearningModuleProgress::with(['user', 'learningClass', 'version.module']), $ids, $user, $historical)->get();
        $rows = [];
        foreach ($lessons as $r) {
            $rows[] = $this->row('lesson-'.$r->id, 'lessons', $r->user, $r->learningClass,
                $r->package ? $r->package->species->common_name.' v'.$r->package->version : $r->lesson_key,
                $r->species_package_id ? 'lesson:'.$r->species_package_id : null, 'Completed', $r->completed_at,
                $r->score, $r->total, ['packageId' => $r->species_package_id, 'source' => $r->source]);
        }
        foreach ($safety as $r) {
            $rows[] = $this->row('safety-'.$r->id, 'safety', $r->user, $r->learningClass,
                ($r->version?->module?->title ?? 'Safety module').' v'.($r->version?->version ?? 'unknown'),
                $r->learning_module_version_id ? 'safety:'.$r->learning_module_version_id : null,
                $r->completed_at ? 'Completed' : 'In progress', $r->updated_at, null, null,
                ['source' => $r->source]);
        }
        $studies = AssessmentStudy::whereIn('class_id', $ids)->get();
        if ($user->role === 'student') {
            $own = DB::table('assessment_attempts')->where('user_id', $user->id)->pluck('assessment_study_id')->all();
            $studies = $studies->filter(fn ($s) => $s->status === 'active' || in_array($s->id, $own));
        }
        $attempts = DB::table('assessment_attempts as a')->join('users as u', 'u.id', '=', 'a.user_id')
            ->whereIn('a.assessment_study_id', $studies->pluck('id'))
            ->when($user->role === 'student', fn ($q) => $q->where('a.user_id', $user->id))
            ->select('a.*', 'u.first_name', 'u.last_name', 'u.identity_number')->get();
        foreach ($attempts as $a) {
            $study = $studies->firstWhere('id', $a->assessment_study_id);
            $person = (object) ['id' => $a->user_id, 'first_name' => $a->first_name, 'last_name' => $a->last_name, 'identity_number' => $a->identity_number];
            $rows[] = $this->row('assessment-'.$a->id, 'assessments', $person, $classes->firstWhere('id', $study->class_id),
                $study->title.' · '.$study->instrument_version.' · '.($a->phase === 'pre' ? 'Pretest' : 'Posttest'),
                'assessment:'.$study->id, $a->voided_at ? 'Voided' : 'Submitted', $a->submitted_at,
                $a->voided_at ? null : $a->score, $a->voided_at ? null : $a->total,
                ['studyId' => $study->id, 'phase' => $a->phase]);
        }
        if ($user->role === 'student') {
            $practice = DB::table('practice_completions as p')->join('species_packages as v', 'v.id', '=', 'p.species_package_id')
                ->join('species as s', 's.id', '=', 'v.species_id')->where('p.user_id', $user->id)->whereIn('p.class_id', $ids)
                ->select('p.*', 'v.version', 's.common_name')->get();
            foreach ($practice as $p) {
                $rows[] = $this->row('practice-'.$p->id, 'practice', $user, $classes->firstWhere('id', $p->class_id),
                    $p->common_name.' v'.$p->version.' · '.str_replace('v1:', '', $p->challenge_key),
                    null, 'Completed', $p->completed_at, null, null, ['xp' => (int) $p->points]);
            }
        }

        $cells = $this->cohort($classes, $lessons, $safety, $user, $now);
        $activities = collect($rows)->merge($cells)->filter(fn ($r) => $r['activityKey'] ?? null)
            ->map(fn ($r) => ['key' => $r['activityKey'], 'name' => $r['activity'], 'kind' => $r['kind'], 'classId' => $r['classId']])
            ->merge($studies->map(fn ($s) => ['key' => 'assessment:'.$s->id, 'name' => $s->title.' · '.$s->instrument_version, 'kind' => 'assessments', 'classId' => $s->class_id]))
            ->groupBy('key')->map(fn ($group) => ['key' => $group->first()['key'], 'name' => $group->first()['name'], 'kind' => $group->first()['kind'],
                'classIds' => $group->pluck('classId')->filter()->unique()->values()->all()])->sortBy('name')->values();
        if (isset($filters['activityKey']) && ! $activities->contains('key', $filters['activityKey'])) {
            abort(403, 'This activity is not available in the selected sections.');
        }
        $permittedLearners = collect($rows)->merge($cells)->pluck('learnerId')->unique();
        if (isset($filters['learnerId']) && ! $permittedLearners->contains((int) $filters['learnerId'])) {
            abort(403);
        }
        $match = fn ($r) => ($filters['activity'] === 'all' || $r['kind'] === $filters['activity'])
            && (! isset($filters['activityKey']) || $r['activityKey'] === $filters['activityKey'])
            && (! isset($filters['learnerId']) || $r['learnerId'] === (int) $filters['learnerId']);
        $cells = array_values(array_filter($cells, $match));
        $from = Carbon::parse($filters['from'], 'Asia/Manila')->startOfDay();
        $to = Carbon::parse($filters['to'], 'Asia/Manila')->endOfDay()->min($now);
        $periodRows = array_values(array_filter($rows, fn ($r) => $match($r) && $r['date'] && Carbon::parse($r['date'])->betweenIncluded($from, $to)));
        usort($periodRows, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: strnatcmp($b['id'], $a['id']));
        $attributed = collect($periodRows)->where('attributed', true);
        $latest = $attributed->where('kind', 'lessons')->filter(fn ($r) => $r['score'] !== null && $r['total'] > 0)
            ->unique(fn ($r) => $r['learnerId'].'|'.$r['classId'].'|'.($r['packageId'] ?? $r['activity']));
        $scores = $latest->map(fn ($r) => round(100 * $r['score'] / $r['total'], 2))->values();
        $completed = count(array_filter($cells, fn ($c) => $c['status'] === 'Completed'));
        $exceptions = count(array_filter($cells, fn ($c) => $c['status'] === 'Exception'));
        $denominator = count($cells) - $exceptions;
        $support = $this->support($cells, $lessons, $safety, $filters, $now);
        $completion = collect($cells)->groupBy('activityKey')->map(fn ($group) => [
            'key' => $group->first()['activityKey'], 'label' => $group->first()['activity'], 'total' => $group->count(),
            'completed' => $group->where('status', 'Completed')->count(), 'exception' => $group->where('status', 'Exception')->count(),
            'inProgress' => $group->where('status', 'In progress')->count(), 'blocked' => $group->where('status', 'Prerequisite pending')->count(),
            'notRecorded' => $group->where('status', 'Not recorded')->count(),
        ])->values()->all();
        $distribution = array_map(fn ($range) => ['label' => $range[0].'–'.$range[1].'%',
            'count' => $scores->filter(fn ($v) => $v >= $range[0] && ($range[1] === 100 ? $v <= 100 : $v < $range[1] + 1))->count()],
            [[0, 19], [20, 39], [40, 59], [60, 79], [80, 100]]);
        $forecast = ['status' => 'selection', 'message' => 'Select one section and an assigned lesson or safety activity to see its completion forecast.', 'history' => [], 'projection' => []];
        if ($user->role === 'student') {
            $forecast['message'] = 'Class forecasts are available to authorized staff. Your own records and next steps are shown here.';
        } elseif (isset($filters['learnerId'])) {
            $forecast['message'] = 'Clear the learner filter to forecast the assigned class cohort.';
        } elseif (isset($filters['classId'], $filters['activityKey']) && preg_match('/^(lesson|safety):/', $filters['activityKey'])) {
            $forecast = $this->forecaster->calculate($cells, (int) $filters['horizon'], $now);
        }
        $assessments = $this->assessmentSummary($studies, $attempts, $filters, $from, $to);
        $enrollments = $classes->flatMap(fn ($c) => $c->status === 'active' ? $c->enrollments->filter(fn ($e) => $e->status === 'active' && $e->user?->role === 'student' && $e->user->isActive()
            && ($user->role !== 'student' || $e->user_id === $user->id) && (! isset($filters['learnerId']) || $e->user_id === (int) $filters['learnerId'])) : []);

        return [
            'meta' => ['generatedAt' => $now->toIso8601String(), 'timezone' => 'Asia/Manila', 'role' => $user->role,
                'institution' => 'Southern Leyte State University', 'campus' => 'Bontoc Campus',
                'scope' => $user->role === 'student' ? 'Your own learning records' : 'Authorized section records',
                'statusBasis' => 'Completion and support use current active accounts, enrollments and assignments at generation time. Dates filter saved records, trends and score summaries.',
                'boundary' => 'Recorded scores and completion estimates do not establish learning effectiveness or practical competence.',
                'practicePrivate' => true],
            'filters' => $filters,
            'options' => ['classes' => $available->map(fn ($c) => $c->only(['id', 'name', 'academic_year', 'semester', 'status']))->all(),
                'academicYears' => $available->pluck('academic_year')->filter()->unique()->values()->all(),
                'semesters' => $available->pluck('semester')->filter()->unique()->values()->all(), 'activities' => $activities->all()],
            'summary' => ['learners' => $enrollments->pluck('user_id')->unique()->count(), 'enrollments' => $enrollments->count(),
                'assignedItems' => count($cells), 'completedItems' => $completed, 'exceptionItems' => $exceptions,
                'completionRate' => $denominator ? round(100 * $completed / $denominator, 1) : null,
                'meanScore' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
                'medianScore' => $scores->isNotEmpty() ? round($scores->median(), 1) : null, 'scoreSamples' => $scores->count(),
                'needsReview' => collect($support)->pluck('learnerId')->unique()->count(), 'recordCount' => count($periodRows),
                'historicalCount' => count($periodRows) - $attributed->count(),
                'practiceXp' => $user->role === 'student' ? (int) $attributed->where('kind', 'practice')->sum('xp') : null],
            'charts' => ['completion' => $completion, 'trend' => $this->trend($attributed, $from, $to), 'distribution' => $distribution],
            'assessments' => $assessments, 'support' => $support, 'forecast' => $forecast, 'rows' => $periodRows,
        ];
    }

    private function scope($query, array $ids, User $user, bool $historical)
    {
        return $query->where(function ($q) use ($ids, $historical) {
            $q->whereIn('class_id', $ids);
            if ($historical) {
                $q->orWhereNull('class_id');
            }
        })
            ->when($user->role === 'student', fn ($q) => $q->where('user_id', $user->id));
    }

    private function row(string $id, string $kind, $person, $class, string $activity, ?string $key, string $status, $date, $score = null, $total = null, array $extra = []): array
    {
        return ['id' => $id, 'kind' => $kind, 'learnerId' => (int) ($person?->id ?? 0),
            'learner' => trim(($person?->first_name ?? '').' '.($person?->last_name ?? '')) ?: 'Retained learner',
            'studentId' => (string) ($person?->identity_number ?? ''), 'classId' => $class?->id,
            'section' => $class?->name ?? 'Unattributed historical record', 'attributed' => $class !== null,
            'activity' => $activity, 'activityKey' => $key, 'status' => $status,
            'score' => $score === null ? null : (int) $score, 'total' => $total === null ? null : (int) $total,
            'date' => $date ? Carbon::parse($date)->toIso8601String() : null] + $extra;
    }

    private function cohort(Collection $classes, Collection $lessons, Collection $safety, User $user, Carbon $now): array
    {
        $overrides = LearningModuleOverride::whereIn('class_id', $classes->pluck('id'))->whereNull('revoked_at')->get();
        $cells = [];
        foreach ($classes->where('status', 'active') as $class) {
            foreach ($class->enrollments->where('status', 'active') as $enrollment) {
                $person = $enrollment->user;
                if (! $person || ! $person->isActive() || $person->role !== 'student' || ($user->role === 'student' && $person->id !== $user->id)) {
                    continue;
                }
                $satisfied = true;
                foreach ($class->moduleAssignments->where('active', true) as $a) {
                    if (! $a->version || ! in_array($a->version->status, ['published', 'archived'], true)) {
                        continue;
                    }
                    $available = $a->starts_at ?? $a->assigned_at;
                    if ($available && $available->gt($now)) {
                        continue;
                    }
                    $progress = $safety->where('class_id', $class->id)->where('user_id', $person->id)
                        ->filter(fn ($p) => $p->version?->learning_module_id === $a->learning_module_id);
                    $progress = $progress->filter(fn ($p) => $p->updated_at->lte($now));
                    $complete = $progress->filter(fn ($p) => $p->completed_at && $p->completed_at->lte($now))->sortBy('completed_at')->first();
                    $override = $overrides->where('class_id', $class->id)->where('learning_module_id', $a->learning_module_id)
                        ->first(fn ($o) => $o->user_id === null || $o->user_id === $person->id);
                    $status = $complete ? 'Completed' : ($override ? 'Exception' : ($progress->isNotEmpty() ? 'In progress' : 'Not recorded'));
                    if ($a->required && ! in_array($status, ['Completed', 'Exception'], true)) {
                        $satisfied = false;
                    }
                    $cells[] = $this->cell($person, $class, 'safety', 'safety:'.$a->learning_module_version_id,
                        ($a->version->module?->title ?? 'Safety module').' v'.$a->version->version, $status,
                        $available, $a->due_at, $complete?->completed_at, $enrollment->created_at);
                }
                foreach ($class->assignments->where('active', true) as $a) {
                    if ($a->package?->status !== 'published') {
                        continue;
                    }
                    $available = $a->starts_at ?? $a->assigned_at;
                    if ($available && $available->gt($now)) {
                        continue;
                    }
                    $results = $lessons->where('user_id', $person->id)->where('class_id', $class->id)->where('species_package_id', $a->species_package_id)
                        ->filter(fn ($r) => $r->completed_at && $r->completed_at->lte($now));
                    $first = $results->sortBy('completed_at')->first();
                    $latest = $results->sortByDesc('id')->sortByDesc('completed_at')->first();
                    $cell = $this->cell($person, $class, 'lessons', 'lesson:'.$a->species_package_id,
                        $a->package->species->common_name.' v'.$a->package->version,
                        $first ? 'Completed' : ($satisfied ? 'Not recorded' : 'Prerequisite pending'),
                        $available, $a->due_at, $first?->completed_at, $enrollment->created_at);
                    $cell['latestScore'] = $latest && $latest->total > 0 ? round(100 * $latest->score / $latest->total, 1) : null;
                    $cells[] = $cell;
                }
            }
        }

        return $cells;
    }

    private function cell($person, $class, string $kind, string $key, string $activity, string $status, $available, $due, $complete, $enrolled): array
    {
        $date = $available ? Carbon::parse($available) : Carbon::parse($enrolled);
        if ($enrolled && Carbon::parse($enrolled)->gt($date)) {
            $date = Carbon::parse($enrolled);
        }

        return ['learnerId' => $person->id, 'learner' => trim($person->first_name.' '.$person->last_name), 'studentId' => (string) $person->identity_number,
            'classId' => $class->id, 'section' => $class->name, 'kind' => $kind, 'activityKey' => $key, 'activity' => $activity,
            'status' => $status, 'availableAt' => $date->toIso8601String(), 'dueAt' => $due?->toIso8601String(),
            'completedAt' => $complete?->toIso8601String(), 'latestScore' => null];
    }

    private function support(array $cells, Collection $lessons, Collection $safety, array $filters, Carbon $now): array
    {
        $result = [];
        foreach ($cells as $cell) {
            $reasons = [];
            $unfinished = ! in_array($cell['status'], ['Completed', 'Exception'], true);
            if ($unfinished && $cell['dueAt'] && Carbon::parse($cell['dueAt'])->lt($now)) {
                $reasons[] = 'Overdue assigned work';
            }
            if ($cell['status'] === 'Prerequisite pending') {
                $reasons[] = 'Safety prerequisite pending';
            }
            $times = $lessons->where('user_id', $cell['learnerId'])->where('class_id', $cell['classId'])->pluck('completed_at')
                ->merge($safety->where('user_id', $cell['learnerId'])->where('class_id', $cell['classId'])->pluck('updated_at'))
                ->filter(fn ($t) => $t && Carbon::parse($t)->lte($now))->sortDesc();
            $last = $times->first();
            $basis = $last ? Carbon::parse($last) : Carbon::parse($cell['availableAt']);
            if ($unfinished && $cell['status'] !== 'Prerequisite pending' && $basis->lte($now->copy()->subDays(7))
                && Carbon::parse($cell['availableAt'])->lte($now->copy()->subDays(7))) {
                $reasons[] = 'No saved learning activity for seven days';
            }
            if (isset($filters['scoreThreshold']) && $cell['latestScore'] !== null && $cell['latestScore'] < (float) $filters['scoreThreshold']) {
                $reasons[] = 'Latest formative score below the selected review threshold';
            }
            if ($reasons) {
                $result[] = $cell + ['reasons' => $reasons, 'lastRecordedAt' => $last ? Carbon::parse($last)->toIso8601String() : null];
            }
        }
        usort($result, fn ($a, $b) => (in_array('Overdue assigned work', $b['reasons']) <=> in_array('Overdue assigned work', $a['reasons'])) ?: strcmp($a['learner'], $b['learner']));

        return $result;
    }

    private function trend(Collection $rows, Carbon $from, Carbon $to): array
    {
        $span = (int) $from->diffInDays($to);
        $unit = $span > 730 ? 'month' : ($span > 90 ? 'week' : 'day');
        $key = fn ($date) => $unit === 'month' ? $date->copy()->startOfMonth()->format('Y-m-d') : ($unit === 'week' ? $date->copy()->startOfWeek()->format('Y-m-d') : $date->format('Y-m-d'));
        $groups = $rows->groupBy(fn ($r) => $key(Carbon::parse($r['date'])->timezone('Asia/Manila')));
        $start = $unit === 'month' ? $from->copy()->startOfMonth() : ($unit === 'week' ? $from->copy()->startOfWeek() : $from->copy());
        $points = [];
        for ($day = $start; $day->lte($to); $day->add($unit, 1)) {
            $group = $groups->get($day->format('Y-m-d'), collect());
            $points[] = ['date' => $day->format('Y-m-d'), 'records' => $group->count(), 'learners' => $group->pluck('learnerId')->unique()->count()];
        }

        return ['interval' => $unit, 'points' => $points];
    }

    private function assessmentSummary(Collection $studies, Collection $attempts, array $filters, Carbon $from, Carbon $to): array
    {
        if (! in_array($filters['activity'], ['all', 'assessments'], true)) {
            return [];
        }
        $result = [];
        foreach ($studies as $s) {
            if (isset($filters['activityKey']) && $filters['activityKey'] !== 'assessment:'.$s->id) {
                continue;
            }
            $all = $attempts->where('assessment_study_id', $s->id)->filter(fn ($a) => Carbon::parse($a->submitted_at)->lte($to)
                && (! isset($filters['learnerId']) || $a->user_id === (int) $filters['learnerId']));
            $pairs = [];
            $unpaired = 0;
            $voided = $all->whereNotNull('voided_at')->count();
            foreach ($all->groupBy('user_id') as $group) {
                if (! $group->contains(fn ($a) => Carbon::parse($a->submitted_at)->betweenIncluded($from, $to))) {
                    continue;
                }
                $valid = $group->whereNull('voided_at');
                $pre = $valid->firstWhere('phase', 'pre');
                $post = $valid->firstWhere('phase', 'post');
                if ($pre && $post && $pre->total > 0 && $pre->total === $post->total) {
                    $pairs[] = ['pre' => 100 * $pre->score / $pre->total, 'post' => 100 * $post->score / $post->total];
                } else {
                    $unpaired++;
                }
            }
            $pre = $pairs ? round(collect($pairs)->avg('pre'), 2) : null;
            $post = $pairs ? round(collect($pairs)->avg('post'), 2) : null;
            $result[] = ['key' => 'assessment:'.$s->id, 'title' => $s->title, 'instrumentVersion' => $s->instrument_version, 'classId' => $s->class_id,
                'matchedPairs' => count($pairs), 'unpairedLearners' => $unpaired, 'voidedAttempts' => $voided,
                'preMean' => $pre, 'postMean' => $post, 'difference' => $pairs ? round($post - $pre, 2) : null];
        }

        return $result;
    }
}
