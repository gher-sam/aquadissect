export function lessonStepPresentation(step = {}, hasIncisionGuide = false) {
  if (step.kind !== 'action-target' || hasIncisionGuide === true) return { ...step, referenceOnly: false };
  return {
    ...step,
    title: 'Review incision planning',
    description: 'This view does not provide a verified incision path. Review the anatomy notes and the intended sequence with your instructor.',
    prompt: 'Select the listed part to review its source notes, then continue. An unavailable outline must not be used to position a physical cut.',
    action: 'Continue the reference review',
    referenceOnly: true,
  };
}
