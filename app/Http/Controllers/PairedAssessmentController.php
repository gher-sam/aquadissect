<?php

namespace App\Http\Controllers;

use App\Models\AssessmentAttempt;
use App\Models\AssessmentEvent;
use App\Models\AssessmentStudy;
use App\Models\ClassEnrollment;
use App\Models\LearningClass;
use App\Models\LessonResult;
use App\Models\SpeciesAssignment;
use App\Models\SpeciesPackage;
use App\Models\User;
use App\Services\DelayedCloneWrite;
use App\Services\SpeciesPackageContent;
use App\Support\WorkspaceAccess;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PairedAssessmentController extends Controller
{
    private function access(Request $r, AssessmentStudy $s, bool $manage = false): void
    {
        if ($manage) {
            WorkspaceAccess::manage($r->user(), $s->class_id);
        } else {
            abort_unless(WorkspaceAccess::classes($r->user())->whereKey($s->class_id)->exists(), 403);
        }
    }

    private function event(AssessmentStudy $s, Request $r, string $action, array $data = []): void
    {
        AssessmentEvent::create(['assessment_study_id' => $s->id, 'user_id' => $r->user()->id, 'action' => $action, 'metadata' => $data]);
    }

    public function index(Request $r)
    {
        $ids = WorkspaceAccess::classes($r->user())->pluck('id');
        $query = AssessmentStudy::whereIn('class_id', $ids);
        if ($r->user()->role === 'student') {
            $query->where(fn ($q) => $q->where('status', 'active')->orWhereIn('id', AssessmentAttempt::where('user_id', $r->user()->id)->select('assessment_study_id')));
        }

        return response()->json(['studies' => $query->orderByDesc('id')->get()->map(fn ($s) => $this->payload($r, $s))]);
    }

    private function payload(Request $r, AssessmentStudy $s): array
    {
        $student = $r->user()->role === 'student';
        $attempts = AssessmentAttempt::where('assessment_study_id', $s->id)
            ->when($student, fn ($q) => $q->where('user_id', $r->user()->id))->orderBy('id')->get();
        $users = $student ? collect([$r->user()->id]) : ClassEnrollment::where('class_id', $s->class_id)->pluck('user_id')->merge($attempts->pluck('user_id'))->unique();
        $rows = $users->map(function ($id) use ($attempts) {
            $all = $attempts->where('user_id', $id);
            $a = $all->whereNull('voided_at');
            $pre = $a->firstWhere('phase', 'pre');
            $post = $a->firstWhere('phase', 'post');

            return ['studentId' => (int) $id, 'preState' => $all->firstWhere('phase', 'pre')?->voided_at ? 'voided' : ($pre ? 'submitted' : 'not_taken'),
                'postState' => $all->firstWhere('phase', 'post')?->voided_at ? 'voided' : ($post ? 'submitted' : 'not_taken'), 'pre' => $pre?->only(['id', 'score', 'total', 'submitted_at']),
                'post' => $post?->only(['id', 'score', 'total', 'submitted_at']),
                'pointDifference' => $pre && $post && $pre->total === $post->total ? $post->score - $pre->score : null,
                'percentagePointDifference' => $pre && $post && $pre->total === $post->total ? round(100 * ($post->score - $pre->score) / $pre->total, 2) : null];
        })->values();

        return ['id' => $s->id, 'title' => $s->title, 'instrumentVersion' => $s->instrument_version, 'classId' => $s->class_id,
            'packageId' => $s->species_package_id, 'status' => $s->status, 'authorId' => $s->author_id, 'reviewerId' => $s->reviewer_id,
            'approvalEvidence' => $student ? null : $s->approval_evidence, 'rows' => $rows,
            'attemptHistory' => $student ? [] : $attempts->map(fn ($a) => $a->only(['id', 'user_id', 'phase', 'score', 'total', 'submitted_at', 'voided_at']))];
    }

    public function store(Request $r, DelayedCloneWrite $writes)
    {
        $d = $r->validate([
            'classId' => 'required|integer|exists:classes,id', 'packageId' => 'required|integer|exists:species_packages,id',
            'title' => 'required|string|max:160', 'instrumentVersion' => 'required|string|max:80',
            'preQuestions' => 'required|array|min:1|max:100', 'postQuestions' => 'required|array|min:1|max:100',
            'preQuestions.*' => 'array:id,prompt,options,answer', 'postQuestions.*' => 'array:id,prompt,options,answer',
            'preQuestions.*.id' => 'required|string|max:80|distinct', 'postQuestions.*.id' => 'required|string|max:80|distinct',
            'preQuestions.*.prompt' => 'required|string|max:3000', 'postQuestions.*.prompt' => 'required|string|max:3000',
            'preQuestions.*.options' => 'required|array|min:2|max:8', 'postQuestions.*.options' => 'required|array|min:2|max:8',
            'preQuestions.*.options.*' => 'required|string|max:1000', 'postQuestions.*.options.*' => 'required|string|max:1000',
            'preQuestions.*.answer' => 'required|string|max:1000', 'postQuestions.*.answer' => 'required|string|max:1000',
        ]);
        WorkspaceAccess::manage($r->user(), $d['classId']);
        app(SpeciesPackageContent::class)->authorize($r->user(), SpeciesPackage::findOrFail($d['packageId']));
        if (count($d['preQuestions']) !== count($d['postQuestions'])) {
            throw ValidationException::withMessages(['questions' => 'Paired forms must have equal maximum scores in this release.']);
        }
        foreach (array_merge($d['preQuestions'], $d['postQuestions']) as $q) {
            if (count(array_unique($q['options'])) !== count($q['options']) || ! in_array($q['answer'], $q['options'], true)) {
                throw ValidationException::withMessages(['questions' => 'Each answer must match one of the distinct options.']);
            }
        }

        return $writes->transaction(function () use ($d, $r) {
            $s = AssessmentStudy::create(['class_id' => $d['classId'], 'species_package_id' => $d['packageId'], 'author_id' => $r->user()->id,
                'title' => $d['title'], 'instrument_version' => $d['instrumentVersion'], 'pre_questions' => $d['preQuestions'],
                'post_questions' => $d['postQuestions'], 'status' => 'draft']);
            $this->event($s, $r, 'created');

            return response()->json(['study' => $this->payload($r, $s)], 201);
        });
    }

    public function action(Request $r, AssessmentStudy $study, string $action, DelayedCloneWrite $writes)
    {
        $this->access($r, $study);
        abort_unless(in_array($action, ['approve', 'activate', 'disable', 'void'], true), 404);

        return $writes->transaction(function () use ($r, $study, $action) {
            $s = AssessmentStudy::whereKey($study->id)->lockForUpdate()->firstOrFail();
            if ($action === 'approve') {
                abort_unless(in_array($r->user()->role, ['instructor', 'program_head', 'admin'], true), 403);
                abort_if($s->author_id === $r->user()->id, 422, 'The author cannot approve this instrument.');
                abort_unless($s->status === 'draft', 422, 'Only a draft can be approved.');
                $d = $r->validate(['evidence' => 'required|string|min:10|max:3000', 'formsValidated' => 'required|accepted', 'policyConfirmed' => 'required|accepted']);
                $s->update(['reviewer_id' => $r->user()->id, 'approval_evidence' => $d['evidence'], 'status' => 'approved']);
            } elseif ($action === 'activate') {
                abort_unless($r->user()->isAdministrator(), 403);
                abort_unless($s->status === 'approved' && $s->reviewer_id && $s->approval_evidence, 422, 'Independent faculty approval is required.');
                $p = SpeciesPackage::findOrFail($s->species_package_id);
                abort_unless($p->status === 'published' && SpeciesAssignment::where('class_id', $s->class_id)->where('species_package_id', $p->id)->where('active', true)->exists(), 422, 'Publish and assign the reviewed lesson first.');
                abort_unless(LearningClass::whereKey($s->class_id)->where('status', 'active')->exists(), 422);
                $s->update(['status' => 'active']);
            } else {
                $this->access($r, $s, true);
                $d = $r->validate(['reason' => 'required|string|min:10|max:1000', 'attemptId' => 'required_if:action,void|nullable|integer']);
                if ($action === 'disable') {
                    $s->update(['status' => 'disabled']);
                } else {
                    $r->validate(['attemptId' => 'required|integer']);
                    $a = AssessmentAttempt::where('assessment_study_id', $s->id)->findOrFail($r->integer('attemptId'));
                    abort_if($a->voided_at, 422, 'Already voided.');
                    $a->update(['voided_at' => now()]);
                }
            }
            $this->event($s, $r, $action, ['reason' => $r->input('reason'), 'attemptId' => $r->input('attemptId')]);

            return response()->json(['study' => $this->payload($r, $s)]);
        });
    }

    public function definition(Request $r, AssessmentStudy $study)
    {
        $this->access($r, $study);

        return response()->json(['preQuestions' => $study->pre_questions, 'postQuestions' => $study->post_questions]);
    }

    public function questions(Request $r, AssessmentStudy $study, string $phase)
    {
        $this->studentReady($r, $study, $phase);
        $questions = $study->{$phase.'_questions'};

        return response()->json(['questions' => array_map(fn ($q) => array_diff_key($q, ['answer' => true]), $questions)]);
    }

    private function studentReady(Request $r, AssessmentStudy $s, string $phase): void
    {
        abort_unless($r->user()->role === 'student', 403);
        $this->access($r, $s);
        abort_unless(in_array($phase, ['pre', 'post'], true), 404);
        abort_unless($s->status === 'active', 403, 'This assessment is not enabled.');
        abort_unless(LearningClass::whereKey($s->class_id)->where('status', 'active')->exists(), 403);
        abort_unless(SpeciesPackage::whereKey($s->species_package_id)->where('status', 'published')->exists(), 403);
        abort_unless(SpeciesAssignment::where('class_id', $s->class_id)->where('species_package_id', $s->species_package_id)->where('active', true)->exists(), 403);
        if ($phase === 'post') {
            abort_unless(AssessmentAttempt::where('assessment_study_id', $s->id)->where('user_id', $r->user()->id)->where('phase', 'pre')->whereNull('voided_at')->exists(), 422, 'Take the pretest first.');
            abort_unless(LessonResult::where('user_id', $r->user()->id)->where('class_id', $s->class_id)->where('species_package_id', $s->species_package_id)->exists(), 422, 'Complete this section lesson before the posttest.');
        }
        abort_if(AssessmentAttempt::where('assessment_study_id', $s->id)->where('user_id', $r->user()->id)->where('phase', $phase)->exists(), 409, 'A scored attempt already exists. Its history is retained.');
    }

    public function submit(Request $r, AssessmentStudy $study, string $phase, DelayedCloneWrite $writes)
    {
        $d = $r->validate(['attemptUuid' => 'required|uuid', 'answers' => 'required|array|max:100', 'answers.*' => 'required|string|max:1000']);
        try {
            return $writes->transaction(function () use ($r, $study, $phase, $d) {
                // Serialize this learner's submissions; database uniqueness also guards concurrent retries.
                User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
                $this->access($r, $study);
                $existing = AssessmentAttempt::where('user_id', $r->user()->id)->where('attempt_uuid', $d['attemptUuid'])->first();
                if ($existing) {
                    abort_unless($r->user()->role === 'student' && $existing->assessment_study_id === $study->id && $existing->phase === $phase, 409);

                    return response()->json(['result' => $existing->only(['id', 'score', 'total', 'phase', 'submitted_at']), 'duplicate' => true]);
                }
                $s = AssessmentStudy::whereKey($study->id)->lockForUpdate()->firstOrFail();
                $this->studentReady($r, $s, $phase);
                $qs = $s->{$phase.'_questions'};
                $ids = array_column($qs, 'id');
                if (count($d['answers']) !== count($qs) || array_diff(array_keys($d['answers']), $ids)) {
                    throw ValidationException::withMessages(['answers' => 'Answer every question in this form.']);
                }
                foreach ($qs as $q) {
                    if (! in_array($d['answers'][$q['id']] ?? null, $q['options'], true)) {
                        throw ValidationException::withMessages(['answers' => 'An answer is not an available option.']);
                    }
                }
                $score = collect($qs)->filter(fn ($q) => $d['answers'][$q['id']] === $q['answer'])->count();
                $a = AssessmentAttempt::create(['assessment_study_id' => $s->id, 'user_id' => $r->user()->id, 'phase' => $phase,
                    'attempt_uuid' => $d['attemptUuid'], 'answers' => $d['answers'], 'score' => $score, 'total' => count($qs), 'submitted_at' => now()]);
                $this->event($s, $r, 'submitted', ['attemptId' => $a->id, 'phase' => $phase]);

                return response()->json(['result' => $a->only(['id', 'score', 'total', 'phase', 'submitted_at']), 'duplicate' => false], 201);
            });
        } catch (QueryException $error) {
            if (in_array((string) $error->getCode(), ['23000', '23505'], true)) {
                $existing = AssessmentAttempt::where('user_id', $r->user()->id)->where('attempt_uuid', $d['attemptUuid'])->first();
                if ($existing && $existing->assessment_study_id === $study->id && $existing->phase === $phase) {
                    return response()->json(['result' => $existing->only(['id', 'score', 'total', 'phase', 'submitted_at']), 'duplicate' => true]);
                }
                abort(409, 'This scored phase already has a retained submission.');
            }
            if (in_array((int) ($error->errorInfo[1] ?? 0), [5, 6], true)) {
                abort(503, 'The records are busy. Retry with the same submission identifier.');
            }
            throw $error;
        }
    }

    public function export(Request $r, AssessmentStudy $study)
    {
        $this->access($r, $study);
        $data = $this->payload($r, $study);

        return response()->streamDownload(function () use ($data) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['Student ID', 'Pre score', 'Pre total', 'Post score', 'Post total', 'Point difference', 'Percentage-point difference']);
            foreach ($data['rows'] as $row) {
                fputcsv($f,[$row['studentId'], $row['pre']['score'] ?? null, $row['pre']['total'] ?? null, $row['post']['score'] ?? null, $row['post']['total'] ?? null, $row['pointDifference'], $row['percentagePointDifference']]);
            }
            fclose($f);
        }, 'assessment-scores.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
