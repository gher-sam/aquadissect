import { gsap } from 'gsap';
import { motionAllowed, onMotionChange } from './motionPreferences.js';

const clearMotionStyles = 'opacity,visibility,transform';

export class LessonMotion {
  constructor(root) {
    this.root = root;
    this.reduceMotion = !motionAllowed();
    this.animations = new Set();
    this.removeMotionListener = onMotionChange(allowed => { this.reduceMotion = !allowed; if (!allowed) this.finishAll(); });
  }

  track(animation) {
    this.animations.add(animation);
    const release = () => this.animations.delete(animation);
    animation.eventCallback('onComplete', release);
    animation.eventCallback('onInterrupt', release);
    return animation;
  }

  finishAll() {
    this.animations.forEach((animation) => animation.progress(1).kill());
    this.animations.clear();
  }

  setProgress(element, percent) {
    if (!element) return;
    gsap.killTweensOf(element);
    if (this.reduceMotion) {
      gsap.set(element, { width: `${percent}%` });
      return;
    }
    this.track(gsap.to(element, {
      width: `${percent}%`,
      duration: 0.4,
      ease: 'power2.out',
      overwrite: true,
    }));
  }

  revealStep(elements, direction = 0) {
    const targets = elements.filter(Boolean);
    if (!targets.length) return;
    gsap.killTweensOf(targets);
    if (this.reduceMotion) {
      gsap.set(targets, { clearProps: clearMotionStyles });
      return;
    }
    this.track(gsap.fromTo(targets, {
      autoAlpha: 0,
      x: direction === 0 ? 0 : direction * 18,
      y: direction === 0 ? 10 : 0,
    }, {
      autoAlpha: 1,
      x: 0,
      y: 0,
      duration: 0.42,
      stagger: 0.035,
      ease: 'power2.out',
      clearProps: clearMotionStyles,
      overwrite: true,
    }));
  }

  revealPart(element) {
    if (!element) return;
    gsap.killTweensOf(element);
    if (this.reduceMotion) {
      gsap.set(element, { clearProps: clearMotionStyles });
      return;
    }
    this.track(gsap.fromTo(element, {
      autoAlpha: 0.45,
      y: 10,
    }, {
      autoAlpha: 1,
      y: 0,
      duration: 0.32,
      ease: 'power2.out',
      clearProps: clearMotionStyles,
      overwrite: true,
    }));
  }

  revealViewer(scene) {
    const viewer = scene?.querySelector('.species-video-viewer, .layered-anatomy-viewer, canvas');
    if (!viewer) return;
    gsap.killTweensOf(viewer);
    if (this.reduceMotion) {
      gsap.set(viewer, { clearProps: clearMotionStyles });
      return;
    }
    this.track(gsap.fromTo(viewer, {
      autoAlpha: 0,
      scale: 0.985,
    }, {
      autoAlpha: 1,
      scale: 1,
      duration: 0.5,
      ease: 'power2.out',
      clearProps: clearMotionStyles,
      overwrite: true,
    }));
  }

  destroy() {
    this.finishAll();
    this.removeMotionListener?.();
    gsap.killTweensOf(this.root.querySelectorAll('*'));
  }
}
