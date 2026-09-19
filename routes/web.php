<?php

use App\Http\Controllers\AdminRosterController;
use App\Http\Controllers\AdminSpeciesPackageController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\InstructorSpeciesPackageController;
use App\Http\Controllers\LearningModuleAssetController;
use App\Http\Controllers\LearningModuleAssignmentController;
use App\Http\Controllers\LearningModuleController;
use App\Http\Controllers\LearningModuleOverrideController;
use App\Http\Controllers\LearningModuleProgressController;
use App\Http\Controllers\LessonAttemptController;
use App\Http\Controllers\LessonResultController;
use App\Http\Controllers\MediaPromptController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SpeciesAssignmentController;
use App\Http\Controllers\SpeciesController;
use App\Http\Controllers\StaffLearningModuleController;
use App\Http\Controllers\StaffSpeciesPackageController;
use Illuminate\Support\Facades\Route;

Route::get('/', PortalController::class);

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/activate', [AuthController::class, 'activate'])->middleware('throttle:registration');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware('auth')->group(function (): void {
        Route::get('/session', [AuthController::class, 'session']);
        Route::get('/species', [SpeciesController::class, 'index']);
        Route::get('/species/{species:slug}/packages/{package}/manifest', [SpeciesController::class, 'manifest']);
        Route::get('/species-packages/{package}/assets/{asset}', [SpeciesController::class, 'asset']);
        Route::get('/species-packages/{package}/download', [SpeciesController::class, 'download']);
        Route::get('/classes', [ClassController::class, 'index']);
        Route::get('/learning-modules', [LearningModuleController::class, 'index']);
        Route::get('/learning-modules/{version}/manifest', [LearningModuleController::class, 'manifest']);
        Route::get('/learning-module-versions/{version}/assets/{asset}', [LearningModuleController::class, 'asset']);
        Route::post('/learning-module-versions/{version}/progress', [LearningModuleProgressController::class, 'store']);
        Route::post('/lessons/{lessonKey}/attempts', [LessonAttemptController::class, 'store']);

        Route::middleware('role:instructor,admin')->prefix('staff')->group(function (): void {
            Route::post('/species-packages/{package}/review', [StaffSpeciesPackageController::class, 'review']);
            Route::post('/learning-module-versions/{version}/submit', [StaffLearningModuleController::class, 'submit']);
            Route::post('/learning-module-versions/{version}/review', [StaffLearningModuleController::class, 'review']);
            Route::post('/learning-module-versions/{version}/assets', [LearningModuleAssetController::class, 'store']);
            Route::post('/media-prompts/preview', [MediaPromptController::class, 'preview']);
            Route::post('/classes/{class}/learning-modules', [LearningModuleAssignmentController::class, 'store']);
            Route::post('/classes/{class}/learning-modules/{module}/overrides', [LearningModuleOverrideController::class, 'store']);
            Route::delete('/learning-module-overrides/{override}', [LearningModuleOverrideController::class, 'destroy']);
        });

        Route::middleware('role:instructor')->prefix('instructor')->group(function (): void {
            Route::post('/species-packages', [InstructorSpeciesPackageController::class, 'store']);
            Route::post('/species-packages/{package}/submit', [InstructorSpeciesPackageController::class, 'submit']);
            Route::post('/classes/{class}/assignments', [SpeciesAssignmentController::class, 'store']);
            Route::delete('/classes/{class}/assignments', [SpeciesAssignmentController::class, 'destroy']);
        });

        Route::middleware('role:admin')->prefix('admin')->group(function (): void {
            Route::get('/roster', [AdminRosterController::class, 'index']);
            Route::post('/offerings', [AdminRosterController::class, 'storeOffering']);
            Route::post('/offerings/{class}/instructors', [AdminRosterController::class, 'assignInstructor']);
            Route::patch('/offerings/{class}/status', [AdminRosterController::class, 'setOfferingStatus']);
            Route::post('/students', [AdminRosterController::class, 'storeStudent']);
            Route::post('/roster/preview', [AdminRosterController::class, 'preview']);
            Route::post('/roster/import', [AdminRosterController::class, 'import']);
            Route::post('/students/{user}/activation', [AdminRosterController::class, 'issueActivation']);
            Route::post('/species-packages/{package}/publish', [AdminSpeciesPackageController::class, 'publish']);
            Route::post('/species-packages/{package}/activate', [AdminSpeciesPackageController::class, 'activate']);
            Route::post('/species-packages/{package}/archive', [AdminSpeciesPackageController::class, 'archive']);
            Route::post('/species-packages/{package}/restore', [AdminSpeciesPackageController::class, 'restore']);
            Route::post('/learning-module-versions/{version}/publish', [StaffLearningModuleController::class, 'publish']);
            Route::post('/learning-module-versions/{version}/archive', [StaffLearningModuleController::class, 'archive']);
        });

        Route::post('/lesson-results', [LessonResultController::class, 'store']);
        Route::post('/admin/users/{user}/approve', [AdminUserController::class, 'approve'])->middleware('role:admin');
    });
});
