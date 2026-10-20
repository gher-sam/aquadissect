// Queued progress is provisional; the server controls lesson access.
export function queuedModuleProgress(progress) {
  const required = new Set(progress.requiredStepIds || []);
  const completed = [...new Set(progress.completedStepIds || [])];
  const activitySubmitted = Boolean(progress.answers || progress.activitySubmitted);
  const allSteps = required.size > 0 && [...required].every(id => completed.includes(id));
  return {
    completedStepIds: completed, completedSteps: completed.length,
    totalSteps: required.size || progress.totalSteps || 0,
    activitySubmitted, completed: allSteps && activitySubmitted,
    completedAt: allSteps && activitySubmitted ? progress.completedAt : null,
    pendingSync: true,
  };
}

export function moduleSaveMessage(result, learner = true) {
  if (!learner) return 'Preview only. No learner result was saved.';
  const score = result?.attempt;
  const prefix = score ? `Score retained: ${score.score} of ${score.total}. ` : '';
  if (result?.queued) return 'Activity saved on this device. Reconnect to synchronize and confirm completion.';
  return prefix + (result?.progress?.completed ? 'Module complete.'
    : 'Activity saved. Finish every remaining required step to complete the module.');
}
