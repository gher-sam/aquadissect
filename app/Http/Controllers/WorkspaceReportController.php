<?php

namespace App\Http\Controllers;

use App\Models\{LessonResult, LearningModuleProgress};
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class WorkspaceReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['classId' => 'nullable|integer', 'from' => 'nullable|date_format:Y-m-d',
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'activity' => 'nullable|in:all,lessons,safety', 'recordedActivity' => 'nullable|string|max:160']);
        $user = $request->user();
        $classes = WorkspaceAccess::classes($user)->get(['id', 'name']);
        if ($request->filled('classId')) abort_unless($classes->contains('id', (int) $request->classId), 403);
        $ids = $request->filled('classId') ? [(int) $request->classId] : $classes->pluck('id');
        $rows = [];
        foreach ([LessonResult::class => 'lessons', LearningModuleProgress::class => 'safety'] as $model => $kind) {
            if (($filters['activity'] ?? 'all') !== 'all' && $filters['activity'] !== $kind) continue;
            $query = $model::with($kind === 'safety' ? ['user', 'learningClass', 'version'] : ['user', 'learningClass'])->where(function ($q) use ($ids, $user, $request) {
                $q->whereIn('class_id', $ids);
                if (!$request->filled('classId') && in_array($user->role, ['admin', 'student'])) $q->orWhereNull('class_id');
            });
            if ($user->role === 'student') $query->where('user_id', $user->id);
            $dateField = $kind === 'lessons' ? 'completed_at' : 'updated_at';
            if ($request->filled('from')) $query->where($dateField, '>=', Carbon::parse($request->from, 'Asia/Manila')->startOfDay()->utc());
            if ($request->filled('to')) $query->where($dateField, '<=', Carbon::parse($request->to, 'Asia/Manila')->endOfDay()->utc());
            foreach ($query->orderByDesc($dateField)->get() as $record) {
                $rows[] = ['kind' => $kind, 'learner' => trim(($record->user?->first_name ?? '').' '.($record->user?->last_name ?? '')),
                    'section' => $record->learningClass?->name ?? 'Unattributed historical record', 'attributed' => $record->class_id !== null,
                    'activity' => $kind === 'lessons' ? $record->lesson_key : 'Safety module v'.($record->version?->version ?? '—'),
                    'score' => $kind === 'lessons' ? $record->score : null, 'total' => $kind === 'lessons' ? $record->total : null,
                    'status' => $record->completed_at ? 'Completed' : 'In progress', 'date' => $record->$dateField?->toIso8601String()];
            }
        }
        $activities = collect($rows)->pluck('activity')->unique()->sort()->values();
        if ($request->filled('recordedActivity')) $rows = array_values(array_filter($rows, fn ($row) => $row['activity'] === $request->recordedActivity));
        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Type', 'Learner', 'Section', 'Activity', 'Score', 'Total', 'Status', 'Date (UTC)']);
                foreach ($rows as $row) {
                    unset($row['attributed']);
                    fputcsv($out, array_map(fn ($v) => preg_match('/^[\s]*[=+@-]/u', (string) $v) ? "'".$v : $v, array_values($row)));
                }
                fclose($out);
            }, 'aquadissect-learning-records.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }
        return response()->json(['rows' => $rows, 'classes' => $classes, 'activities' => $activities]);
    }
}
