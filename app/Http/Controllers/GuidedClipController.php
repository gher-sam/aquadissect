<?php

namespace App\Http\Controllers;

use App\Models\SpeciesPackage;
use App\Services\GuidedClipDrafts;
use App\Services\GuidedClipVideo;
use App\Support\LearningMedia;
use Illuminate\Http\Request;

class GuidedClipController extends Controller
{
    public function create(Request $request, SpeciesPackage $package, GuidedClipDrafts $drafts)
    {
        return response()->json($drafts->create($package, $request->user()))->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, SpeciesPackage $package, string $draft, GuidedClipDrafts $drafts)
    {
        return response()->json($drafts->read($package, $request->user(), $draft))->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, SpeciesPackage $package, string $draft, GuidedClipDrafts $drafts)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:1', 'steps' => 'required|array|min:1|max:150',
            'steps.*' => 'required|array:id,title,description,prompt,action,videoAsset',
            'steps.*.id' => ['required', 'string', 'distinct', 'regex:/^(s[0-9]+|n-[a-f0-9]{32})$/D'],
            'steps.*.title' => 'required|string|max:200', 'steps.*.description' => 'required|string|max:5000',
            'steps.*.prompt' => 'required|string|max:2000', 'steps.*.action' => 'required|string|max:100',
            'steps.*.videoAsset' => ['present', 'nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_-]+$/D'],
        ]);

        return response()->json($drafts->update($package, $request->user(), $draft, $data))->header('Cache-Control', 'private, no-store');
    }

    public function upload(Request $request, SpeciesPackage $package, string $draft, GuidedClipDrafts $drafts, GuidedClipVideo $video)
    {
        $data = $request->validate([
            'revision' => 'required|integer|min:1', 'stepId' => 'required|string|max:100',
            'asset' => 'required|file|mimes:mp4,webm|max:81920',
            'caption' => 'required|string|max:1000', 'source' => 'required|string|max:500',
            'licence' => 'required|string|max:300', 'aiGenerated' => 'required|boolean',
        ]);

        return response()->json($drafts->upload($package, $request->user(), $draft, $data, $video))->header('Cache-Control', 'private, no-store');
    }

    public function asset(Request $request, SpeciesPackage $package, string $draft, string $asset, GuidedClipDrafts $drafts)
    {
        $path = $drafts->asset($package, $request->user(), $draft, $asset);

        return response()->file($path, array_merge(LearningMedia::headers(), ['Content-Type' => 'video/mp4']))->setPrivate();
    }

    public function commit(Request $request, SpeciesPackage $package, string $draft, GuidedClipDrafts $drafts)
    {
        $data = $request->validate(['revision' => 'required|integer|min:1', 'version' => ['required', 'string', 'max:40', 'regex:/^\d+\.\d+\.\d+(?:-[a-zA-Z0-9.-]+)?$/D']]);

        return response()->json($drafts->commit($package, $request->user(), $draft, $data))->header('Cache-Control', 'private, no-store');
    }

    public function destroy(Request $request, SpeciesPackage $package, string $draft, GuidedClipDrafts $drafts)
    {
        return response()->json($drafts->cancel($package, $request->user(), $draft))->header('Cache-Control', 'private, no-store');
    }
}
