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

Route::get('/offline', fn () => view('app', ['page' => 'downloads', 'bootstrap' => ['offlineShell' => true]]))->name('offline');

Route::get('/', fn (\Illuminate\Http\Request $request) => redirect()->route($request->user() ? 'portal.home' : 'login'));
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/auth/google/{intent}', [\App\Http\Controllers\GoogleAuthController::class, 'redirect'])
    ->where('intent', 'signin|activate|register')->middleware('throttle:registration')->name('google.redirect');
Route::get('/auth/google/callback', [\App\Http\Controllers\GoogleAuthController::class, 'callback'])->middleware('throttle:registration')->name('google.callback');
Route::post('/api/register/google', [\App\Http\Controllers\GoogleAuthController::class, 'register'])->middleware('throttle:registration');
foreach (\App\Support\PortalPages::PAGES as $page => [$path, $roles]) {
    $route = Route::get('/'.$path, PortalController::class)->defaults('page', $page)->name($page === 'login' ? 'login' : 'portal.'.$page);
    if ($roles) $route->middleware(['auth', 'role:'.$roles]);
}

Route::prefix('api')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/activate', [AuthController::class, 'activate'])->middleware('throttle:registration');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware(['auth', 'role:student,instructor,program_head,admin'])->group(function (): void {
        Route::middleware('role:student,instructor,program_head,admin')->group(function (): void {
            Route::get('/tutorials', [\App\Http\Controllers\TutorialController::class, 'index']);
            Route::put('/tutorials/{guide}/progress', [\App\Http\Controllers\TutorialController::class, 'update']);
        });
        Route::middleware('role:instructor,program_head,admin')->prefix('staff')->group(function (): void {
            Route::post('/species-packages/{package}/review', [StaffSpeciesPackageController::class, 'review']);
            Route::post('/learning-module-versions/{version}/review', [StaffLearningModuleController::class, 'review']);
        });
        Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index']);
        Route::post('/calendar/events', [\App\Http\Controllers\CalendarController::class, 'store']);
        Route::patch('/calendar/events/{event}', [\App\Http\Controllers\CalendarController::class, 'update']);
        Route::post('/calendar/events/{event}/cancel', [\App\Http\Controllers\CalendarController::class, 'cancel']);
        Route::get('/notifications', [\App\Http\Controllers\WorkspaceNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [\App\Http\Controllers\WorkspaceNotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [\App\Http\Controllers\WorkspaceNotificationController::class, 'read']);
        Route::get('/reports', [\App\Http\Controllers\WorkspaceReportController::class, 'index']);
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
        Route::post('/lessons/{lessonKey}/attempts', [LessonAttemptController::class, 'store'])->middleware('role:student,instructor,admin');

        Route::middleware('role:instructor,admin')->prefix('staff')->group(function (): void {
            Route::post('/learning-module-versions/{version}/submit', [StaffLearningModuleController::class, 'submit']);
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
            Route::get('/staff-roles', [\App\Http\Controllers\StaffRoleController::class, 'index']);
            Route::patch('/staff-roles/{user}', [\App\Http\Controllers\StaffRoleController::class, 'update']);
            Route::get('/source-handover', [\App\Http\Controllers\SourceHandoverController::class, 'index']);
            Route::get('/source-handover/preview', [\App\Http\Controllers\SourceHandoverController::class, 'preview'])->middleware('throttle:60,1,source-preview');
            Route::get('/source-handover/download', [\App\Http\Controllers\SourceHandoverController::class, 'download'])->middleware('throttle:6,1,source-zip');
            Route::get('/source-handover/file', [\App\Http\Controllers\SourceHandoverController::class, 'file'])->middleware('throttle:30,1,source-file');
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

        Route::post('/lesson-results', [LessonResultController::class, 'store'])->middleware('role:student,instructor,admin');
        Route::post('/admin/users/{user}/approve', [AdminUserController::class, 'approve'])->middleware('role:admin');
    });
});
