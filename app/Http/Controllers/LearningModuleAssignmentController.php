<?php

namespace App\Http\Controllers;

use App\Models\ClassInstructor;
use App\Models\ClassModuleAssignment;
use App\Models\LearningClass;
use App\Models\LearningModuleVersion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningModuleAssignmentController extends Controller
{
    public function store(Request $request, LearningClass $class): JsonResponse
    {
        $user = $request->user();
        if ($user->role === User::ROLE_INSTRUCTOR) {
            abort_unless(ClassInstructor::query()->where(['class_id' => $class->id, 'user_id' => $user->id])->exists(), 403);
        }
        $data = $request->validate([
            'versionId' => ['required', 'integer', 'exists:learning_module_versions,id'],
            'required' => ['sometimes', 'boolean'],
            'dueAt' => ['nullable', 'date'],
        ]);
        $version = LearningModuleVersion::query()->whereKey($data['versionId'])
            ->where('status', LearningModuleVersion::STATUS_PUBLISHED)->firstOrFail();
        $assignment = ClassModuleAssignment::query()->updateOrCreate(
            ['class_id' => $class->id, 'learning_module_id' => $version->learning_module_id],
            [
                'learning_module_version_id' => $version->id,
                'assigned_by' => $user->id,
                'required' => $data['required'] ?? true,
                'active' => true,
                'assigned_at' => now(),
                'due_at' => $data['dueAt'] ?? null,
            ],
        );

        return response()->json(['assignment' => $assignment], 201);
    }
}
