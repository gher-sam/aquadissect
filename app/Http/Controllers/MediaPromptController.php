<?php

namespace App\Http\Controllers;

use App\Models\LearningModuleVersion;
use App\Models\MediaPromptRecord;
use App\Services\MediaPromptBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MediaPromptController extends Controller
{
    public function preview(Request $request, MediaPromptBuilder $builder): JsonResponse
    {
        $data = $request->validate([
            'versionId' => ['required', 'integer', 'exists:learning_module_versions,id'],
            'toolKey' => ['required', 'string', 'max:100'],
            'mediaType' => ['required', Rule::in(['illustration', 'video'])],
            'view' => ['required', Rule::in(['side', 'three-quarter', 'top'])],
            'action' => ['required', Rule::in(['identification', 'proper-handling', 'storage-and-disposal'])],
            'ppe' => ['required', Rule::in(['tool-only', 'gloved-hands', 'full-ppe-context'])],
            'attach' => ['sometimes', 'boolean'],
        ]);
        $version = LearningModuleVersion::query()->with('module')->findOrFail($data['versionId']);
        abort_if(in_array($version->status, [LearningModuleVersion::STATUS_PUBLISHED, LearningModuleVersion::STATUS_ARCHIVED], true), 422, 'Prompts can only be attached to a draft or review version.');
        $prompt = $builder->build($version, $data);
        $record = null;
        if ($data['attach'] ?? false) {
            $record = MediaPromptRecord::query()->create([
                'learning_module_version_id' => $version->id,
                'actor_id' => $request->user()->id,
                'tool_key' => $data['toolKey'],
                'media_type' => $data['mediaType'],
                'prompt_text' => $prompt,
                'options' => collect($data)->except(['attach', 'versionId'])->all(),
                'attached_at' => now(),
            ]);
        }

        return response()->json(['prompt' => $prompt, 'recordId' => $record?->id]);
    }
}
