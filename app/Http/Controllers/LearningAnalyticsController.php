<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearningReportRequest;
use App\Services\Reports\LearningReport;
use App\Services\Reports\ReportExports;
use Illuminate\Http\JsonResponse;

class LearningAnalyticsController extends Controller
{
    public function analytics(LearningReportRequest $request, LearningReport $reports): JsonResponse
    {
        $report = $reports->build($request->user(), $request->validated());
        unset($report['rows']);

        return response()->json($report)->header('Cache-Control', 'private, no-store');
    }

    public function records(LearningReportRequest $request, LearningReport $reports): JsonResponse
    {
        $data = $request->validated();
        $report = $reports->build($request->user(), $data);
        $rows = collect($report['rows']);
        if ($request->filled('search')) {
            $search = mb_strtolower($data['search']);
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower(implode(' ', [$r['learner'], $r['studentId'], $r['section'], $r['activity'], $r['status']])), $search));
        }
        $rows = match ($data['sort'] ?? 'date_desc') {
            'date_asc' => $rows->sortBy('date'), 'learner' => $rows->sortBy('learner'), default => $rows,
        };
        $size = (int) ($data['perPage'] ?? 25);
        $page = (int) ($data['page'] ?? 1);

        return response()->json(['rows' => $rows->slice(($page - 1) * $size, $size)->values(), 'total' => $rows->count(),
            'page' => $page, 'perPage' => $size, 'lastPage' => max(1, (int) ceil($rows->count() / $size))])
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(LearningReportRequest $request, LearningReport $reports, ReportExports $exports)
    {
        $request->validate(['format' => 'required|in:pdf,xlsx,csv,docx,xml']);

        return $exports->download($reports->build($request->user(), $request->validated()), $request->input('format'), $request->input('layout', 'summary'));
    }
}
