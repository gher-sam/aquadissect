// Reveal each stage once as it enters the viewport; content stays readable without motion.
export function animateSourceTimeline(figure) {
  if (!figure || !('IntersectionObserver' in window)) return () => {};
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  const stages = [...figure.querySelectorAll('.source-delivery-flow > li, figcaption')];
  const seen = new Set();
  let observer;

  const configure = () => {
    observer?.disconnect();
    stages.forEach(stage => stage.classList.remove('is-timeline-entering'));
    if (motion.matches) return;
    observer = new IntersectionObserver(entries => {
      const entering = entries.filter(entry => entry.isIntersecting)
        .sort((a, b) => stages.indexOf(a.target) - stages.indexOf(b.target));
      entering.forEach((entry, index) => {
        const stage = entry.target;
        stage.style.setProperty('--timeline-delay', `${index * 120}ms`);
        stage.classList.add('is-timeline-entering');
        seen.add(stage);
        observer.unobserve(stage);
      });
    }, { threshold: 0.15 });
    stages.filter(stage => !seen.has(stage)).forEach(stage => observer.observe(stage));
  };
  motion.addEventListener('change', configure);
  configure();
  return () => {
    observer?.disconnect();
    motion.removeEventListener('change', configure);
    stages.forEach(stage => {
      stage.classList.remove('is-timeline-entering');
      stage.style.removeProperty('--timeline-delay');
    });
  };
}
