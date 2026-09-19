<?php

namespace App\Http\Controllers;

use App\Models\LearningModuleVersion;
use App\Services\LearningModuleAssetStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningModuleAssetController extends Controller
{
    public function store(Request $request, LearningModuleVersion $version, LearningModuleAssetStore $store): JsonResponse
    {
        $data = $request->validate([
            'assetKey' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:120'],
            'asset' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,mp4,webm', 'max:81920'],
            'source' => ['required', 'string', 'max:500'],
            'licence' => ['required', 'string', 'max:300'],
            'caption' => ['required', 'string', 'max:1000'],
            'aiGenerated' => ['required', 'boolean'],
        ]);
        $asset = $store->store($version->load('module'), $data['asset'], $data['assetKey'], $data);

        return response()->json(['asset' => $asset], 201);
    }
}
