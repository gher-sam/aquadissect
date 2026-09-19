import { gsap } from 'gsap';

const contexts = new WeakMap();

export function revealPortalPage(panel) {
  if (!panel || panel.hidden) return;
  contexts.get(panel)?.revert();
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const context = gsap.context(() => {
    const heading = panel.querySelector('.dashboard-hero, .portal-page-title');
    if (heading) {
      gsap.fromTo(heading, { autoAlpha: 0, y: 8 }, {
        autoAlpha: 1,
        y: 0,
        duration: 0.22,
        ease: 'power2.out',
        clearProps: 'opacity,visibility,transform',
      });
    }

    const cards = panel.querySelectorAll('.motion-feature, .species-card, .status-card, .metric-card, .module-grid > article, .learning-module-manager-card, .learning-module-card, .class-card, .assignment-card, .package-import-card, .workflow-disclosure, .prompt-studio-form, .prompt-output, .access-card, .flow-grid > article, .admin-table-card, .admin-operations-card');
    if (cards.length) {
      gsap.fromTo(cards, { autoAlpha: 0, y: 12 }, {
        autoAlpha: 1,
        y: 0,
        duration: 0.3,
        stagger: 0.035,
        ease: 'power2.out',
        clearProps: 'opacity,visibility,transform',
      });
    }

    const orbits = panel.querySelectorAll('.motion-orbit');
    if (orbits.length) {
      gsap.fromTo(orbits, { scale: 0.96, opacity: 0 }, {
        scale: 1,
        opacity: 0.72,
        duration: 0.45,
        stagger: 0.06,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      });
    }
  }, panel);

  contexts.set(panel, context);
}
