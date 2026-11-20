import '@fontsource/figtree/latin-500.css';
import '@fontsource/figtree/latin-700.css';
import '@fontsource/figtree/latin-800.css';
import '../css/reef-assessment.css';
import '../css/reef-demo.css';
import { createElement, Fish, BookOpen, Shell, Flag } from 'lucide';
import { animateFeedback, setupMotionControls } from './motionPreferences.js';
import { diveComparisonMarkup, openDive, reefAssessmentSnapshot } from './portal/reefAssessment.js';
import { createDemoSession } from './reefDemoSession.js';

let session = createDemoSession();
const stagePanel = document.querySelector('#demo-stage');
const steps = ['pre', 'lesson', 'post'];
const screens = {
  pre: { name: 'First Dive', type: 'Pre-test', action: 'Start First Dive', icon: Fish },
  lesson: { name: 'A quick look at fish anatomy', type: 'Sample lesson', action: 'Finish sample lesson', icon: BookOpen },
  post: { name: 'Return Dive', type: 'Post-test', action: 'Start Return Dive', icon: Shell },
  complete: { name: 'Your dive comparison', type: 'Dive log', action: 'Try both dives again', icon: Flag },
};

function icon(shape) {
  return createElement(shape, { 'aria-hidden': 'true', focusable: 'false' });
}

function stageContent(stage) {
  switch (stage) {
    case 'pre':
      return '<p>Answer four sample questions, one at a time. You can change your choices before you submit.</p>';
    case 'lesson':
      return `<p>You scored <strong>${session.firstScore.score} / ${session.firstScore.total}</strong> in First Dive. Review these features, then try Return Dive.</p>
        <dl class="demo-lesson">
          <div><dt>Gills and gill cover</dt><dd>Gills take up oxygen from water. The bony flap that covers them is called the operculum.</dd></div>
          <div><dt>Fins</dt><dd>The pectoral fins sit just behind the gill covers. The dorsal fin runs along the back, and the caudal fin forms the tail.</dd></div>
          <div><dt>Lateral line</dt><dd>This sensory system runs along the side of the fish and detects water movement.</dd></div>
        </dl>`;
    case 'post':
      return '<p>Try four more sample questions. After you submit, you’ll see both scores and the difference between them.</p>';
    case 'complete':
      return `<div class="rq-comparison">${diveComparisonMarkup(session.comparison())}</div>`;
  }
}

function renderStage(focus = true) {
  const { stage } = session;
  const screen = screens[stage];
  stagePanel.dataset.stage = stage;
  const current = stage === 'complete' ? steps.length : steps.indexOf(stage);
  document.querySelectorAll('[data-step]').forEach((item, index) => {
    item.dataset.done = String(index < current);
    if (index === current) item.setAttribute('aria-current', 'step');
    else item.removeAttribute('aria-current');
    item.querySelector('span').textContent = index < current ? '✓' : String(index + 1);
  });
  stagePanel.innerHTML = `<div class="demo-card-head"><span class="demo-emblem"></span><div><p class="demo-type">${screen.type}</p>
    <h2 id="stage-title" tabindex="-1">${screen.name}</h2></div></div>
    ${stageContent(stage)}<button type="button" class="demo-action">${screen.action}</button>`;
  stagePanel.querySelector('.demo-emblem').append(icon(screen.icon));
  if (focus) {
    stagePanel.querySelector('h2').focus();
    animateFeedback(stagePanel, [{ opacity: .8, transform: 'translateY(5px)' }, { opacity: 1, transform: 'none' }], { duration: 220 });
  }
}

stagePanel.addEventListener('click', event => {
  const launcher = event.target.closest('.demo-action');
  if (!launcher) return;
  const phase = session.stage;
  if (phase === 'lesson') {
    session.finishLesson();
    renderStage();
  } else if (phase === 'complete') {
    session = createDemoSession();
    renderStage();
  } else {
    const currentSession = session;
    openDive({
      phase, launcher, demo: true, source: session.source(phase),
      study: { title: 'Student demo', instrumentVersion: 'Fish anatomy' },
      isCurrent: () => session === currentSession, refresh: renderStage,
    });
  }
});

setupMotionControls();
document.querySelector('[data-fish]').append(icon(Fish));
renderStage(false);
window.addEventListener('pageshow', event => {
  if (event.persisted) renderStage(false);
});
window.render_game_to_text = () => JSON.stringify({
  stage: session.stage, assessment: reefAssessmentSnapshot(), firstScore: session.firstScore?.score ?? null,
  comparison: session.comparison()?.pointDifference ?? null,
});
