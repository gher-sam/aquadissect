<?php

namespace App\Http\Controllers;

use App\Models\TutorialProgress;
use App\Services\TutorialCatalog;
use App\Services\DelayedCloneWrite;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TutorialController extends Controller
{
    public function index(Request $request, TutorialCatalog $catalog)
    {
        return response()->json($catalog->for($request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, string $guide, TutorialCatalog $catalog, DelayedCloneWrite $writes)
    {
        $definition = collect($catalog->guides($request->user()->role))->firstWhere('id', $guide);
        abort_unless($definition, 404);
        $data = $request->validate([
            'version' => ['required', 'integer', Rule::in([$definition['version']])],
            'step' => ['required', Rule::in(array_column($definition['steps'], 'id'))],
            'viewed' => ['required', 'boolean'],
        ]);
        if ($data['viewed']) abort_unless($data['step'] === end($definition['steps'])['id'], 422);
        return $writes->transaction(function () use ($request, $guide, $data) {
            // Serialize updates per account and preserve the original viewed timestamp on retries.
            $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $progress = TutorialProgress::firstOrNew(['user_id' => $request->user()->id, 'guide_key' => $guide, 'version' => $data['version']]);
            $progress->step_key = $data['step'];
            if ($data['viewed'] && !$progress->viewed_at) $progress->viewed_at = now();
            $progress->save();
            return response()->json(['progress' => $progress->only(['guide_key', 'version', 'step_key', 'viewed_at'])]);
        });
    }
}
