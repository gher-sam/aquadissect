<?php

namespace App\Services;

use App\Models\{CalendarEvent, ClassEnrollment, ClassInstructor, ClassModuleAssignment, SpeciesAssignment, User, WorkspaceNotification, SpeciesPackage, LearningModuleVersion, SpeciesPackageReview, LearningModuleReview};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WorkspaceNotifications
{
    public function saved(Model $model): void
    {
        if (!Schema::hasTable('workspace_notifications')) return;
        $created = $model->wasRecentlyCreated && !array_key_exists($model->getKeyName(), $model->getOriginal());
        $changed = array_keys($model->getDirty());
        $classId = null;
        $recipients = collect();
        $destination = 'home';
        if ($model instanceof CalendarEvent || $model instanceof SpeciesAssignment || $model instanceof ClassModuleAssignment) {
            if (!$created && !array_intersect($changed, ['title', 'description', 'location', 'starts_at', 'ends_at', 'due_at', 'active', 'required', 'cancelled_at', 'species_package_id', 'learning_module_version_id'])) return;
            $classId = $model->class_id;
            $recipients = ClassEnrollment::where('class_id', $classId)->where('status', 'active')->pluck('user_id')
                ->merge(ClassInstructor::where('class_id', $classId)->pluck('user_id'));
            $destination = $model instanceof CalendarEvent ? 'calendar' : 'classes';
            $summary = $model instanceof CalendarEvent ? ($model->cancelled_at ? 'A class event was cancelled.' : 'A class event was added or updated.') : 'A class activity was added or updated.';
        } elseif ($model instanceof User) {
            if ($model->role !== 'instructor') return;
            if ($created && $model->status === 'pending') {
                $recipients = User::where('role', 'admin')->pluck('id');
                $summary = 'An instructor access request needs review.';
            } elseif (!$created && in_array('status', $changed, true) && $model->status === 'active') {
                $recipients = collect([$model->id]); $summary = 'Your instructor account has been approved.';
            } else return;
        } elseif ($model instanceof SpeciesPackage || $model instanceof LearningModuleVersion || $model instanceof SpeciesPackageReview || $model instanceof LearningModuleReview) {
            $review = $model instanceof SpeciesPackageReview || $model instanceof LearningModuleReview;
            if (!$review && (!in_array('status', $changed, true) || !in_array($model->status, ['review', 'published', 'archived']))) return;
            if ($review && !$created) return;
            $recipients = User::whereIn('role', ['instructor', 'admin'])->pluck('id');
            $destination = $model instanceof LearningModuleVersion || $model instanceof LearningModuleReview ? 'safety' : 'manager';
            $summary = $review ? 'A faculty content-review decision was recorded.' : 'Learning content status changed to '.$model->status.'.';
        } else return;
        $key = hash('sha256', get_class($model).':'.$model->getKey().':'.json_encode($model->getAttributes()));
        foreach (User::whereIn('id', $recipients->unique())->where('status', 'active')->pluck('id') as $id) {
            WorkspaceNotification::firstOrCreate(['user_id' => $id, 'event_key' => $key], [
                'type' => class_basename($model), 'summary' => $summary, 'destination' => $destination, 'class_id' => $classId,
            ]);
        }
    }
}
