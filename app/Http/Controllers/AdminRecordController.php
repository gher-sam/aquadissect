<?php

namespace App\Http\Controllers;

use App\Models\AdminRecordBatch;
use App\Services\AdminRecordManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminRecordController extends Controller
{
    public function index(Request $request, AdminRecordManager $manager): JsonResponse
    {
        $input = $request->validate([
            'resource' => ['required', Rule::in(array_keys(AdminRecordManager::MODELS))],
            'view' => ['sometimes', Rule::in(['current', 'archived', 'all'])],
            'q' => ['nullable', 'string', 'max:120'], 'page' => ['sometimes', 'integer', 'min:1'],
            'perPage' => ['sometimes', 'integer', Rule::in([10, 25, 50, 100])],
        ]);
        $resource = $input['resource'];
        $query = $manager->query($resource);
        $view = $input['view'] ?? 'current';
        if ($view !== 'all') {
            $view === 'archived' ? $query->whereIn('status', ['archived', 'withdrawn']) : $query->whereNotIn('status', ['archived', 'withdrawn']);
        }
        $term = trim($input['q'] ?? '');
        if ($term !== '') {
            $query->where(function ($q) use ($resource, $term): void {
                $like = '%'.$term.'%';
                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
                match ($resource) {
                    'students' => $q->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('identity_number', 'like', $like),
                    'enrollments' => $q->orWhereHas('user', fn ($u) => $u->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('identity_number', 'like', $like))
                        ->orWhereHas('learningClass', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like)),
                    'sections' => $q->orWhere('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('academic_year', 'like', $like),
                    'species-versions' => $q->orWhere('version', 'like', $like)->orWhereHas('species', fn ($s) => $s->where('common_name', 'like', $like)->orWhere('slug', 'like', $like)),
                    'safety-versions' => $q->orWhere('version', 'like', $like)->orWhereHas('module', fn ($m) => $m->where('title', 'like', $like)),
                };
            });
        }
        $relations = match ($resource) {
            'enrollments' => ['user', 'learningClass'], 'species-versions' => ['species'], 'safety-versions' => ['module'], default => [],
        };
        $page = $query->with($relations)->orderBy('id')->paginate($input['perPage'] ?? 25);

        return response()->json([
            'rows' => $page->getCollection()->map(fn ($row) => ['id' => $row->id, 'label' => $manager->label($resource, $row), 'status' => $row->status]),
            'page' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total(),
            'audit' => AdminRecordBatch::query()->latest('id')->limit(20)->get()->map(fn ($batch) => [
                'id' => $batch->id, 'actorId' => $batch->actor_id, 'resource' => $batch->resource_type,
                'action' => $batch->action, 'count' => count($batch->record_ids), 'reason' => $batch->reason,
                'createdAt' => $batch->created_at?->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function preview(Request $request, AdminRecordManager $manager): JsonResponse
    {
        $input = $request->validate([
            'resource' => ['required', Rule::in(array_keys(AdminRecordManager::MODELS))],
            'action' => ['required', Rule::in(['archive', 'restore', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $input['ids'] = array_map('intval', $input['ids']);

        return response()->json($manager->preview($request, $input))->header('Cache-Control', 'private, no-store');
    }

    public function execute(Request $request, AdminRecordManager $manager): JsonResponse
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:20000'], 'requestUuid' => ['required', 'uuid'],
            'confirmation' => ['required', Rule::in(['CONFIRM', 'DELETE'])],
        ]);

        return response()->json($manager->execute($request, $input))->header('Cache-Control', 'private, no-store');
    }
}
