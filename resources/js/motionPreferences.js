export const motionPreferenceKey = 'aquadissect.motion.v1';
export const resolveMotionPreference = (saved, reduced = false) => saved !== 'off' && !reduced;

let initialized = false, preference = null, query = null;
const listeners = new Set();
const animations = new Set();

export function motionAllowed() {
  if (typeof window === 'undefined') return false;
  return resolveMotionPreference(preference, (query || window.matchMedia('(prefers-reduced-motion: reduce)')).matches);
}

export function onMotionChange(listener) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

export function motionPreferenceQuery() {
  const subscriptions = new Map();
  return {
    get matches() { return !motionAllowed(); },
    addEventListener(type, listener) { if (type === 'change' && !subscriptions.has(listener)) subscriptions.set(listener, onMotionChange(allowed => listener({ matches: !allowed }))); },
    removeEventListener(type, listener) { if (type === 'change') { subscriptions.get(listener)?.(); subscriptions.delete(listener); } },
  };
}

export function animateFeedback(element, frames, options = {}) {
  if (!element?.isConnected || !motionAllowed() || !element.animate) return;
  const animation = element.animate(frames, { duration: 450, easing: 'ease-out', ...options });
  animations.add(animation);
  animation.finished.then(() => animations.delete(animation), () => animations.delete(animation));
  return animation;
}

export function setupMotionControls() {
  if (initialized || typeof window === 'undefined') return;
  initialized = true;
  query = window.matchMedia('(prefers-reduced-motion: reduce)');
  try { preference = localStorage.getItem(motionPreferenceKey); } catch { /* Device storage is optional. */ }
  function update() {
    const allowed = motionAllowed();
    document.documentElement.dataset.aqMotion = allowed ? 'on' : 'off';
    document.querySelectorAll('[data-aq-motion-toggle]').forEach(button => {
      button.setAttribute('aria-pressed', String(allowed));
      button.setAttribute('aria-label', query.matches ? 'Motion off: reduced motion is enabled on this device' : `Turn ${allowed ? 'off' : 'on'} decorative motion`);
      button.disabled = query.matches;
      button.querySelector('[data-aq-motion-label]').textContent = `Motion ${allowed ? 'on' : 'off'}`;
    });
    if (!allowed) {
      animations.forEach(animation => animation.cancel()); animations.clear();
      document.querySelectorAll('.motion-feature-video, .species-card-image video').forEach(video => video.pause());
    }
    listeners.forEach(listener => listener(allowed));
    window.dispatchEvent(new CustomEvent('aquadissect-motion-change', { detail: { allowed } }));
  }
  document.addEventListener('click', event => {
    if (!event.target.closest?.('[data-aq-motion-toggle]')) return;
    preference = motionAllowed() ? 'off' : 'on';
    try { localStorage.setItem(motionPreferenceKey, preference); } catch { /* Keep the setting in memory. */ }
    update();
  });
  window.addEventListener('storage', event => { if (event.key === motionPreferenceKey) { preference = event.newValue; update(); } });
  query.addEventListener?.('change', update);
  update();
}
