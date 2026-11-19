export function createDiveAnswers(questions) {
  const selections = Array(questions.length).fill(null);
  let submission = null;

  return {
    selected(index) { return selections[index]; },
    get count() { return selections.filter(choice => choice !== null).length; },
    choose(index, choice) {
      if (submission || !Number.isInteger(choice) || questions[index]?.options[choice] === undefined) return;
      selections[index] = choice;
    },
    submission(createId) {
      if (!questions.length || this.count !== questions.length) return null;
      if (!submission) {
        const answers = Object.fromEntries(questions.map((question, index) =>
          [question.id, question.options[selections[index]]]));
        submission = Object.freeze({ attemptUuid: createId(), answers: Object.freeze(answers) });
      }
      return submission;
    },
  };
}

export function recordedPair(studies, studyId, studentId, resultId) {
  const row = studies.find(study => study.id === studyId)?.rows.find(learner => learner.studentId === studentId);
  if (!row || row.preState !== 'submitted' || row.postState !== 'submitted' || row.post?.id !== resultId ||
      !row.pre || row.pre.total !== row.post.total || !Number.isFinite(row.pointDifference) ||
      !Number.isFinite(row.percentagePointDifference)) return null;
  return row;
}
