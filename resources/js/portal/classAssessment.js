import { request } from './api.js';
import { openDive } from './reefAssessment.js';
import { recordedPair } from './reefAssessmentAnswers.js';
import { pendingSignOutKey, signOutPending } from './sessionAccess.js';

export function openReefAssessment({ study, phase, owner, launcher, isCurrent, refresh }) {
  const endpoint = `/api/paired-assessments/${study.id}/${phase}`;
  openDive({
    study, phase, launcher, refresh, isCurrent: () => isCurrent() && !signOutPending(),
    source: {
      async questions(signal) {
        return (await request(endpoint + '/questions', { signal })).questions;
      },
      async submit(body, signal) {
        return (await request(endpoint + '/attempts', { method: 'POST', body, signal })).result;
      },
      async comparison(resultId, signal) {
        const { studies } = await request('/api/paired-assessments', { signal });
        return recordedPair(studies, study.id, owner, resultId);
      },
    },
    listenForAccessLoss(close, signal) {
      window.addEventListener('aquadissect-authorization-lost', close, { signal });
      window.addEventListener('aquadissect-offline-media-locked', event => {
        if (event.detail?.ownerId == null || event.detail.ownerId === owner) close();
      }, { signal });
      window.addEventListener('storage', event => {
        if (event.key === pendingSignOutKey && event.newValue === '1') close();
      }, { signal });
    },
  });
}
