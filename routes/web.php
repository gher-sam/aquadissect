<?php

use App\Http\Controllers\AdminRosterController;
use App\Http\Controllers\AdminRecordController;
use App\Http\Controllers\AdminSpeciesPackageController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\FishRevisionController;
use App\Http\Controllers\GmailSenderController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\InstructorSpeciesPackageController;
use App\Http\Controllers\LearningModuleAssetController;
use App\Http\Controllers\LearningModuleAssignmentController;
use App\Http\Controllers\LearningModuleController;
use App\Http\Controllers\LearningModuleOverrideController;
use App\Http\Controllers\LearningModuleProgressController;
use App\Http\Controllers\LessonAttemptController;
use App\Http\Controllers\LessonResultController;
use App\Http\Controllers\MediaPromptController;
use App\Http\Controllers\PairedAssessmentController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SourceHandoverController;
use App\Http\Controllers\SpeciesAssignmentController;
use App\Http\Controllers\SpeciesController;
use App\Http\Controllers\StaffLearningModuleController;
use App\Http\Controllers\StaffRoleController;
use App\Http\Controllers\StaffSpeciesPackageController;
use App\Http\Controllers\StudentCredentialController;
use App\Http\Controllers\StudentEmailSetupController;
use App\Http\Controllers\StudentPasswordSetupController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\WorkspaceNotificationController;
use App\Http\Controllers\WorkspaceReportController;
use App\Support\PortalPages;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/offline', fn () => view('app', ['page' => 'downloads', 'bootstrap' => ['offlineShell' => true]]))->name('offline');

Route::get('/', fn (Request $request) => redirect()->route($request->user() ? 'portal.home' : 'login'));
Route::view('/privacy', 'privacy')->name('privacy');
Route::get('/change-password', [StudentCredentialController::class, 'edit'])->middleware('auth')->name('password.change');
Route::post('/change-password', [StudentCredentialController::class, 'update'])->middleware(['auth', 'throttle:student-password-change'])->name('password.update');
Route::post('/sign-out', [StudentCredentialController::class, 'signOut'])->middleware('auth')->name('password.sign-out');
Route::get('/admin/email-delivery', [GmailSenderController::class, 'index'])
    ->middleware(['auth', 'role:admin'])->name('gmail-sender.index');
Route::post('/auth/gmail/connect', [GmailSenderController::class, 'redirect'])
    ->middleware(['auth', 'role:admin', 'throttle:registration'])->name('gmail-sender.connect');
Route::get('/auth/google/{intent}', [GoogleAuthController::class, 'redirect'])
    ->where('intent', 'signin|activate|register')->middleware('throttle:registration')->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('throttle:registration')->name('google.callback');
Route::post('/api/register/google', [GoogleAuthController::class, 'register'])->middleware('throttle:registration');
foreach (PortalPages::PAGES as $page => [$path, $roles]) {
    $route = Route::get('/'.$path, PortalController::class)->defaults('page', $page)->name($page === 'login' ? 'login' : 'portal.'.$page);
    if ($roles) {
        $route->middleware(['auth', 'role:'.$roles]);
    }
}

Route::prefix('api')->group(function (): void {
    Route::post('/change-password', [StudentCredentialController::class, 'update'])->middleware(['auth', 'throttle:student-password-change']);
    Route::prefix('student-password-setup')->middleware('throttle:student-setup')->group(function (): void {
        Route::get('/status', [StudentPasswordSetupController::class, 'status']);
        Route::post('/claim', [StudentPasswordSetupController::class, 'claim'])->middleware('throttle:student-setup-claim');
        Route::post('/complete', [StudentPasswordSetupController::class, 'complete']);
        Route::post('/cancel', [StudentEmailSetupController::class, 'cancel']);
    });
    Route::prefix('student-setup')->middleware('throttle:student-setup')->group(function (): void {
        Route::get('/status', [StudentEmailSetupController::class, 'status']);
        Route::post('/claim', [StudentEmailSetupController::class, 'claim'])->middleware('throttle:student-setup-claim');
        Route::post('/email', [StudentEmailSetupController::class, 'send']);
        Route::post('/verify', [StudentEmailSetupController::class, 'verify']);
        Route::post('/complete', [StudentEmailSetupController::class, 'complete']);
        Route::post('/cancel', [StudentEmailSetupController::class, 'cancel']);
    });
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('/activate', [AuthController::class, 'activate'])->middleware('throttle:registration');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

    Route::middleware(['auth', 'role:student,instructor,program_head,admin'])->group(function (): void {
        Route::middleware('role:student,instructor,program_head,admin')->group(function (): void {
            Route::get('/tutorials', [TutorialController::class, 'index']);
            Route::put('/tutorials/{guide}/progress', [TutorialController::class, 'update']);
        });
        Route::middleware('role:instructor,program_head,admin')->prefix('staff')->group(function (): void {
            Route::post('/species-packages/{package}/review', [StaffSpeciesPackageController::class, 'review']);
            Route::post('/learning-module-versions/{version}/review', [StaffLearningModuleController::class, 'review']);
        });
        Route::get('/calendar', [CalendarController::class, 'index']);
        Route::post('/calendar/events', [CalendarController::class, 'store']);
        Route::patch('/calendar/events/{event}', [CalendarController::class, 'update']);
        Route::post('/calendar/events/{event}/cancel', [CalendarController::class, 'cancel']);
        Route::get('/notifications', [WorkspaceNotificationController::class, 'index']);
        Route::post('/notifications/read-all', [WorkspaceNotificationController::class, 'readAll']);
        Route::post('/notifications/{notification}/read', [WorkspaceNotificationController::class, 'read']);
        Route::get('/reports', [WorkspaceReportController::class, 'index']);
        Route::get('/session', [AuthController::class, 'session']);
        Route::get('/paired-assessments', [PairedAssessmentController::class, 'index']);
        Route::get('/paired-assessments/{study}/export', [PairedAssessmentController::class, 'export']);
        Route::get('/paired-assessments/{study}/{phase}/questions', [PairedAssessmentController::class, 'questions']);
        Route::post('/paired-assessments/{study}/{phase}/attempts', [PairedAssessmentController::class, 'submit']);
        Route::get('/paired-assessments/{study}/definition', [PairedAssessmentController::class, 'definition'])->middleware('role:instructor,program_head,admin');
        Route::post('/paired-assessments', [PairedAssessmentController::class, 'store'])->middleware('role:instructor,admin');
        Route::post('/paired-assessments/{study}/actions/{action}', [PairedAssessmentController::class, 'action'])->middleware('role:instructor,program_head,admin');
        Route::post('/staff/species-packages/{package}/revision', [FishRevisionController::class, 'store'])->middleware('role:instructor,admin');
        Route::get('/species', [SpeciesController::class, 'index']);
        Route::get('/species/{species:slug}/packages/{package}/manifest', [SpeciesController::class, 'manifest']);
        Route::get('/species-packages/{package}/card-preview', [SpeciesController::class, 'cardPreview']);
        Route::get('/species-packages/{package}/assets/{asset}', [SpeciesController::class, 'asset']);
        Route::get('/species-packages/{package}/download', [SpeciesController::class, 'download']);
        Route::get('/classes', [ClassController::class, 'index']);
        Route::get('/learning-modules', [LearningModuleController::class, 'index']);
        Route::get('/learning-modules/{version}/manifest', [LearningModuleController::class, 'manifest']);
        Route::get('/learning-module-versions/{version}/assets/{asset}', [LearningModuleController::class, 'asset']);
        Route::post('/learning-module-versions/{version}/progress', [LearningModuleProgressController::class, 'store']);
        Route::post('/lessons/{lessonKey}/attempts', [LessonAttemptController::class, 'store'])->middleware('role:student,instructor,admin');

        Route::middleware('role:instructor,admin')->prefix('staff')->group(function (): void {
            Route::post('/students/{user}/reset-password', [StudentCredentialController::class, 'reset']);
            Route::get('/students/{user}/password-history', [StudentCredentialController::class, 'history']);
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
            Route::get('/records', [AdminRecordController::class, 'index']);
            Route::post('/records/preview', [AdminRecordController::class, 'preview'])->middleware('throttle:30,1,record-preview');
            Route::post('/records/execute', [AdminRecordController::class, 'execute'])->middleware('throttle:10,1,record-execute');
            Route::get('/staff-roles', [StaffRoleController::class, 'index']);
            Route::patch('/staff-roles/{user}', [StaffRoleController::class, 'update']);
            Route::get('/source-handover', [SourceHandoverController::class, 'index']);
            Route::get('/source-handover/preview', [SourceHandoverController::class, 'preview'])->middleware('throttle:60,1,source-preview');
            Route::get('/source-handover/download', [SourceHandoverController::class, 'download'])->middleware('throttle:6,1,source-zip');
            Route::get('/source-handover/file', [SourceHandoverController::class, 'file'])->middleware('throttle:30,1,source-file');
            Route::get('/roster', [AdminRosterController::class, 'index']);
            Route::post('/offerings', [AdminRosterController::class, 'storeOffering']);
            Route::post('/offerings/{class}/instructors', [AdminRosterController::class, 'assignInstructor']);
            Route::patch('/offerings/{class}/status', [AdminRosterController::class, 'setOfferingStatus']);
            Route::post('/students', [AdminRosterController::class, 'storeStudent']);
            Route::post('/roster/preview', [AdminRosterController::class, 'preview']);
            Route::post('/roster/uploads', [AdminRosterController::class, 'inspectUpload'])->middleware('throttle:10,1');
            Route::post('/roster/uploads/{draft}/map', [AdminRosterController::class, 'mapUpload']);
            Route::post('/roster/uploads/{draft}/review', [AdminRosterController::class, 'reviewUpload']);
            Route::post('/roster/uploads/{draft}/import', [AdminRosterController::class, 'importUpload']);
            Route::delete('/roster/uploads/{draft}', [AdminRosterController::class, 'cancelUpload']);
            Route::post('/roster/import', [AdminRosterController::class, 'import']);
            Route::post('/students/{user}/activation', [AdminRosterController::class, 'issueActivation']);
            Route::patch('/students/{user}/email', [AdminRosterController::class, 'addStudentEmail']);
            Route::post('/student-setup-codes', [StudentEmailSetupController::class, 'issue']);
            Route::post('/species-packages/{package}/submit', [InstructorSpeciesPackageController::class, 'submit']);
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
