// These public examples are independent of class assessment forms.
const forms = {
  pre: [
    { id: 'cover', prompt: 'Which structure covers the gills?', options: ['Operculum', 'Caudal fin', 'Lateral line', 'Pelvic fin'], key: 'Operculum' },
    { id: 'pectoral', prompt: 'Which pair of fins sits just behind the gill covers?', options: ['Dorsal fins', 'Pectoral fins', 'Caudal fins', 'Anal fins'], key: 'Pectoral fins' },
    { id: 'tail', prompt: 'Which fin forms the tail of the fish?', options: ['Pelvic fin', 'Anal fin', 'Caudal fin', 'Dorsal fin'], key: 'Caudal fin' },
    { id: 'movement', prompt: 'Which structure senses water movement along the side of the fish?', options: ['Scales', 'Operculum', 'Eye', 'Lateral line'], key: 'Lateral line' },
  ],
  post: [
    { id: 'movement', prompt: 'Which structure detects a change in the movement of nearby water?', options: ['Pectoral fin', 'Lateral line', 'Operculum', 'Caudal fin'], key: 'Lateral line' },
    { id: 'oxygen', prompt: 'Which organ takes up oxygen from the water?', options: ['Stomach', 'Eye', 'Gills', 'Liver'], key: 'Gills' },
    { id: 'back', prompt: 'Which fin lies along the top of the fish?', options: ['Anal fin', 'Caudal fin', 'Pelvic fin', 'Dorsal fin'], key: 'Dorsal fin' },
    { id: 'pectoral', prompt: 'Which paired fins are found just behind the gill covers?', options: ['Pectoral fins', 'Pelvic fins', 'Dorsal fins', 'Anal fins'], key: 'Pectoral fins' },
  ],
};

export function createDemoSession() {
  let stage = 'pre';
  const scores = { pre: null, post: null };

  function comparison() {
    const { pre, post } = scores;
    if (!pre || !post) return null;
    return {
      pre, post, pointDifference: post.score - pre.score,
      percentagePointDifference: 100 * (post.score / post.total - pre.score / pre.total),
    };
  }

  return {
    get stage() { return stage; },
    get firstScore() { return scores.pre; },
    comparison,
    finishLesson() { if (stage === 'lesson') stage = 'post'; },
    source(phase) {
      if (!Object.hasOwn(forms, phase)) throw new Error('Unknown dive.');
      const items = forms[phase];
      function checkStage() {
        if (stage !== phase) throw new Error('Follow the dive sequence on the demo page.');
      }
      return {
        questions() {
          checkStage();
          return items.map(({ id, prompt, options }) => ({ id, prompt, options: [...options] }));
        },
        submit({ attemptUuid, answers }) {
          if (scores[phase] && scores[phase].id === attemptUuid) return scores[phase];
          checkStage();
          if (!attemptUuid || !items.every(q => q.options.includes(answers?.[q.id]))) {
            throw new Error('Choose an answer for every question.');
          }
          const score = items.filter(q => answers[q.id] === q.key).length;
          scores[phase] = Object.freeze({ id: attemptUuid, phase, score, total: items.length });
          stage = phase === 'pre' ? 'lesson' : 'complete';
          return scores[phase];
        },
        comparison,
      };
    },
  };
}
