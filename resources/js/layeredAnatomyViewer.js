import { motionAllowed, onMotionChange } from './motionPreferences.js';
import { ImageMeasurement } from './imageMeasurement.js';
import { AnatomyRegionOverlay } from './anatomyRegionOverlay.js';

const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

export class LayeredAnatomyViewer {
  constructor(container, anatomy, manifest, { onSelect, onDepthChange, onPlaybackChange } = {}) {
    this.container = container;
    this.anatomy = anatomy;
    this.manifest = manifest;
    this.onSelect = onSelect;
    this.onDepthChange = onDepthChange;
    this.onPlaybackChange = onPlaybackChange;
    this.depth = 0;
    this.step = 0;
    this.labelsAll = false;
    this.highlighted = new Set();
    this.selectedId = null;
    this.playing = false;
    this.playDirection = 1;
    this.reduceMotion = !motionAllowed();
    this.removeMotionListener = onMotionChange(allowed => { this.reduceMotion = !allowed; if (!allowed && this.playing) this.stopPlayback(); if (!allowed && this.controls) this.controls.autoRotate = false; });
    this.structures = Object.values(anatomy);
    this.externalIds = this.structures.filter((item) => item.layer === 'external').map((item) => item.id);
    this.internalIds = this.structures.filter((item) => item.layer === 'internal').map((item) => item.id);
    this.stepDepths = manifest.contentData.lesson.stepDepths || [];

    const assets = manifest.assetRecords;
    this.root = document.createElement('div');
    this.root.className = 'layered-anatomy-viewer';
    this.root.innerHTML = `
      <div class="layered-image-frame">
        <img class="anatomy-layer anatomy-layer--natural">
        <img class="anatomy-layer anatomy-layer--external">
        <img class="anatomy-layer anatomy-layer--internal">
      </div>
    `;
    container.prepend(this.root);
    this.frame = this.root.querySelector('.layered-image-frame');
    this.naturalImage = this.root.querySelector('.anatomy-layer--natural');
    this.externalImage = this.root.querySelector('.anatomy-layer--external');
    this.internalImage = this.root.querySelector('.anatomy-layer--internal');
    for (const layer of ['natural', 'external', 'internal']) {
      const image = this.root.querySelector(`.anatomy-layer--${layer}`);
      image.src = assets[layer].url; image.alt = `${layer} educational image of ${manifest.species.commonName}`;
    }
    this.regions = new AnatomyRegionOverlay(this.frame, manifest, this.structures, onSelect);
    this.measurement = new ImageMeasurement(this.frame, manifest, key => ({natural:this.naturalImage,external:this.externalImage,internal:this.internalImage})[key]);
    this.bindInteractions();
    this.layoutObserver = new ResizeObserver(() => this.layoutHotspots());
    this.layoutObserver.observe(this.frame);
    this.setDepth(0, false);
    this.ready = Promise.all([...this.root.querySelectorAll('img')].map((image) => image.decode?.().catch(() => undefined))).then(() => this.layoutHotspots());
  }

  bindInteractions() {
    this.handleWheel = (event) => {
      if (Math.abs(event.deltaY) < 1) return;
      event.preventDefault();
      this.stopPlayback();
      this.setDepth(this.depth + Math.sign(event.deltaY) * 8);
    };
    this.handleKeydown = (event) => {
      if (!['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown'].includes(event.key)) return;
      event.preventDefault();
      this.stopPlayback();
      const direction = ['ArrowDown', 'PageDown'].includes(event.key) ? 1 : -1;
      this.setDepth(this.depth + direction * 10);
    };
    this.container.addEventListener('wheel', this.handleWheel, { passive: false });
    this.container.addEventListener('keydown', this.handleKeydown);
  }

  layerState(depth = this.depth) {
    if (depth < 22) return { name: 'Natural specimen · 2.5D', key: 'natural' };
    if (depth < 67) return { name: 'External anatomy · 2.5D', key: 'external' };
    return { name: 'Internal anatomy · 2.5D', key: 'internal' };
  }

  setDepth(value, announce = true) {
    this.depth = clamp(Number(value), 0, 100);
    this.naturalImage.style.opacity = clamp(1 - this.depth / 28, 0, 1);
    this.externalImage.style.opacity = this.depth <= 42
      ? clamp(this.depth / 24, 0, 1)
      : clamp(1 - (this.depth - 42) / 43, 0, 1);
    this.internalImage.style.opacity = clamp((this.depth - 52) / 38, 0, 1);
    this.frame.style.setProperty('--image-scale', '1');
    this.updateHotspots();
    this.measurement?.update(this.layerState().key);
    if (announce) this.onDepthChange?.(this.depth, this.layerState());
  }

  setStep(step) {
    this.step = clamp(step, 0, Math.max(0, this.stepDepths.length - 1));
    this.setDepth(this.stepDepths[this.step] ?? 0);
  }

  hasIncisionGuide() { return false; }

  traceIncision() {
    // Source-specific incision geometry awaits review; no generic path is drawn.
    this.setDepth(58);
  }

  openBodyWall() {
    this.setDepth(100);
  }

  setExplode(value) { this.stopPlayback(); this.setDepth(Number(value) * 100); }

  setLabelsVisible(visible) {
    this.labelsAll = visible;
    this.root.classList.toggle('show-image-labels', visible);
    this.updateHotspots();
  }

  setHighlighted(ids) { this.highlighted = new Set(ids); this.updateHotspots(); }

  selectPart(id) {
    this.stopPlayback();
    this.selectedId = id;
    const layer = this.anatomy[id]?.layer;
    const targetDepth = layer === 'internal' ? 100 : 34;
    if (layer && this.depth !== targetDepth) this.setDepth(targetDepth);
    else this.updateHotspots();
  }

  regionStatus(id) { return this.regions.status(id); }
  regionSnapshot() { return { ...this.regions.snapshot(), playing:this.playing }; }

  layoutHotspots() {
    const key = this.layerState().key;
    const image = {natural:this.naturalImage,external:this.externalImage,internal:this.internalImage}[key];
    this.regions.update(image, key, this.selectedId, this.highlighted, this.labelsAll);
  }

  updateHotspots() {
    this.layoutHotspots();
  }

  resetView() { this.stopPlayback(); this.setDepth(this.stepDepths[this.step] ?? 0); }

  togglePlayback() {
    if (this.reduceMotion) return false;
    if (this.playing) { this.stopPlayback(); return false; }
    this.playing = true;
    this.onPlaybackChange?.(true);
    let lastTime = null;
    const animate = (timestamp) => {
      if (!this.playing) return;
      if (lastTime != null) {
        const next = this.depth + this.playDirection * Math.min(60, timestamp - lastTime) * 0.025;
        if (next >= 100 || next <= 0) this.playDirection *= -1;
        this.setDepth(clamp(next, 0, 100));
      }
      lastTime = timestamp;
      this.animationFrame = requestAnimationFrame(animate);
    };
    this.animationFrame = requestAnimationFrame(animate);
    return true;
  }

  stopPlayback() {
    const wasPlaying = this.playing;
    this.playing = false;
    if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
    this.animationFrame = null;
    if (wasPlaying) this.onPlaybackChange?.(false);
  }

  async toggleFullscreen() {
    if (document.fullscreenElement) { await document.exitFullscreen(); return false; }
    await this.container.parentElement.requestFullscreen(); return true;
  }

  destroy() {
    this.removeMotionListener?.();
    this.layoutObserver?.disconnect();
    this.measurement?.destroy();
    this.stopPlayback();
    this.regions.destroy();
    this.container.removeEventListener('wheel', this.handleWheel);
    this.container.removeEventListener('keydown', this.handleKeydown);
    this.root.remove();
  }
}
