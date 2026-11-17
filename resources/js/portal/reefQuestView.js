import { escapeHtml as e } from './format.js';

export function questContexts(classes = [], packages = []) {
  const seen = new Set();
  return classes.filter(c => c.status === 'active').flatMap(section => (section.assignments || []).flatMap(assignment => {
    const record = packages.find(p => String(p.id) === String(assignment.packageId));
    const key = `${section.id}:${assignment.packageId}`;
    if (!record || assignment.active === false || record.status !== 'published' || seen.has(key)) return [];
    seen.add(key);
    return [{ key, section, record, ready: section.prerequisiteStatus?.satisfied === true && !assignment.locked }];
  }));
}

export function questBadgeView(badges = []) {
  return badges.map((badge, index) => `<li class="reef-badge ${badge.earned ? 'is-earned' : ''}"><span class="reef-badge-icon reef-badge-${index % 4}"><i data-lucide="${badge.earned ? ['shell', 'search-check', 'network', 'award'][index % 4] : 'lock-keyhole'}" aria-hidden="true"></i></span><span><strong>${e(badge.name)}</strong><small>${badge.earned ? 'Earned' : 'To earn'}</small></span></li>`).join('');
}

export function reefDashboardView() {
  return `<section class="reef-quest" id="reefQuest" aria-labelledby="reefTitle">
    <section class="reef-hero"><div><span class="reef-eyebrow">Explore. Practice. Discover.</span><h2 id="reefTitle">Your next discovery awaits.</h2><p>Follow your assigned learning trail, explore anatomy, and collect private practice rewards.</p><button class="portal-primary reef-main-action" type="button" data-quest-continue disabled>Choose your activity <i data-lucide="arrow-right" aria-hidden="true"></i></button></div><div class="reef-hero-art" hidden><img alt=""><span class="reef-art-label"><i data-lucide="fish" aria-hidden="true"></i><span data-quest-species></span></span></div></section>
    <section class="reef-trail-card"><div class="reef-card-heading"><h2>Your learning trail</h2><span class="reef-scope-label">Current assigned work</span></div><label class="reef-context-label">Section and lesson<select data-quest-context aria-label="Choose section and lesson"></select></label><ol class="reef-trail"><li><span class="reef-step" data-quest-safety-icon><i data-lucide="shield-check" aria-hidden="true"></i></span><strong>Safety &amp; Tools</strong><small data-quest-safety>Choose a lesson</small></li><li><span class="reef-step"><i data-lucide="fish" aria-hidden="true"></i></span><strong>Guided anatomy</strong><small data-quest-lesson>Choose a lesson</small></li><li><span class="reef-step reef-step-gold"><i data-lucide="puzzle" aria-hidden="true"></i></span><strong>Optional practice</strong><small data-quest-practice>Choose a lesson</small></li></ol><p class="reef-context-status" data-quest-status role="status" aria-live="polite"></p></section>
    <div class="reef-home-grid"><section class="reef-practice-card"><div class="reef-card-heading"><span class="reef-feature-icon"><i data-lucide="puzzle" aria-hidden="true"></i></span><h2>Make practice a discovery.</h2></div><p>Try Function finder and System sorter. Hints and retries are always available.</p><div class="reef-reward-summary"><span><strong data-quest-xp>Not confirmed</strong><small>Selected lesson practice XP</small></span><span><strong data-quest-count>Not confirmed</strong><small>Challenges completed</small></span></div><button class="portal-primary" type="button" data-quest-practice-open disabled>Open practice <i data-lucide="arrow-up-right" aria-hidden="true"></i></button></section><section class="reef-collection-card"><div class="reef-card-heading"><h2>Your badge collection</h2><span class="reef-gold-label">Private rewards</span></div><ul class="reef-badge-list" data-quest-badges></ul><p class="reef-context-status" data-quest-badge-note>Select a section and lesson to confirm your collection.</p></section></div>
    <p class="reef-boundary">Practice rewards belong to your account, section and lesson version. They are separate from grades, safety completion and formal tests.</p>
  </section>`;
}
