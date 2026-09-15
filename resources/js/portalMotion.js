import { gsap } from 'gsap';

const contexts = new WeakMap();

export function revealPortalPage(panel) {
  if (!panel || panel.hidden) return;
  contexts.get(panel)?.revert();
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const context = gsap.context(() => {
    const cards = panel.querySelectorAll('.motion-feature, .species-card, .status-card, .metric-card, .module-grid > article');
    if (cards.length) {
      gsap.fromTo(cards, { autoAlpha: 0, y: 18 }, {
        autoAlpha: 1,
        y: 0,
        duration: 0.58,
        stagger: 0.07,
        ease: 'power2.out',
        clearProps: 'opacity,visibility,transform',
      });
    }

    const orbits = panel.querySelectorAll('.motion-orbit');
    if (orbits.length) {
      gsap.to(orbits, {
        scale: 1.06,
        opacity: 0.78,
        duration: 2.2,
        repeat: -1,
        yoyo: true,
        stagger: 0.25,
        ease: 'sine.inOut',
      });
    }
  }, panel);

  contexts.set(panel, context);
}
