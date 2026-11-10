export function editableClipSteps(steps) {
  return steps.map(({ id, title, description, prompt, action, videoAsset }) => ({ id, title, description, prompt, action, videoAsset: videoAsset || null }));
}

export function moveClipStep(steps, id, direction) {
  const index = steps.findIndex(step => step.id === id);
  if (index < 0 || !steps[index].clipOnly || ![-1, 1].includes(direction)) return steps;
  const target = index + direction;
  if (target < 0 || target >= steps.length || steps[target].terminal) return steps;
  const moved = [...steps];
  [moved[index], moved[target]] = [moved[target], moved[index]];
  return moved;
}

export function facultyWordingPrompt(step) {
  return `Rephrase the faculty-authored instructions below for MB 304L students. Use clear, detailed, plain English based only on the supplied facts. Preserve the actions, their order, anatomical terms, measurements, specimen position, tools and every safety warning. Do not invent procedural actions or claim scientific validation. Flag missing or ambiguous details for faculty clarification. Return suggested wording for the instructions only. A faculty member will compare and accept the suggestion before saving.\n\nStep: ${step.title}\nFaculty instructions:\n${step.description}\n\nLearner task: ${step.prompt}\nNext action: ${step.action}`;
}

export function acceptFacultySuggestion(steps, id, suggestion) {
  if (!suggestion.trim() || suggestion.length > 5000) throw new Error('Paste a suggestion of no more than 5,000 characters.');
  return steps.map(step => step.id === id ? { ...step, description: suggestion.trim() } : step);
}
