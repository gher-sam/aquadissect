<?php

namespace App\Http\Controllers;

use App\Models\ClassInstructor;
use App\Models\LearningClass;
use App\Models\LearningModule;
use App\Models\LearningModuleOverride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningModuleOverrideController extends Controller
{
    public function store(Request $request, LearningClass $class, LearningModule $module): JsonResponse
    {
        $this->authorizeClass($request, $class);
        $data = $request->validate([
            'studentId' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if (isset($data['studentId'])) {
            abort_unless($class->enrollments()->where('user_id', $data['studentId'])->where('status', 'active')->exists(), 422, 'The learner is not actively enrolled in this section.');
        }
        $existing = LearningModuleOverride::query()->where([
            'class_id' => $class->id,
            'user_id' => $data['studentId'] ?? null,
            'learning_module_id' => $module->id,
        ])->whereNull('revoked_at')->first();
        if ($existing) {
            return response()->json(['override' => $existing, 'duplicate' => true]);
        }
        $override = LearningModuleOverride::query()->create([
            'class_id' => $class->id,
            'user_id' => $data['studentId'] ?? null,
            'learning_module_id' => $module->id,
            'reason' => $data['reason'],
            'granted_by' => $request->user()->id,
            'granted_at' => now(),
        ]);

        return response()->json(['override' => $override, 'duplicate' => false], 201);
    }

    public function destroy(Request $request, LearningModuleOverride $override): JsonResponse
    {
        $class = LearningClass::query()->findOrFail($override->class_id);
        $this->authorizeClass($request, $class);
        if (! $override->revoked_at) {
            $override->update(['revoked_by' => $request->user()->id, 'revoked_at' => now()]);
        }

        return response()->json(['override' => $override->fresh()]);
    }

    private function authorizeClass(Request $request, LearningClass $class): void
    {
        if ($request->user()->role === User::ROLE_ADMIN) {
            return;
        }
        abort_unless(ClassInstructor::query()->where(['class_id' => $class->id, 'user_id' => $request->user()->id])->exists(), 403);
    }
}
