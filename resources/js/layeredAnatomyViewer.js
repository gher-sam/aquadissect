import { ImageMeasurement } from './imageMeasurement.js';
import { fittedImageRect } from './imageMeasurementData.js';

const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

export class LayeredAnatomyViewer {
  constructor(container, anatomy, manifest, { onSelect, onDepthChange } = {}) {
    this.container = container;
    this.anatomy = anatomy;
    this.manifest = manifest;
    this.onSelect = onSelect;
    this.onDepthChange = onDepthChange;
    this.depth = 0;
    this.step = 0;
    this.labelsAll = false;
    this.highlighted = new Set();
    this.selectedId = null;
    this.playing = false;
    this.playDirection = 1;
    this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.structures = Object.values(anatomy);
    this.externalIds = this.structures.filter((item) => item.layer === 'external').map((item) => item.id);
    this.internalIds = this.structures.filter((item) => item.layer === 'internal').map((item) => item.id);
    this.stepDepths = manifest.contentData.lesson.stepDepths || [];

    const assets = manifest.assetRecords;
    this.root = document.createElement('div');
    this.root.className = 'layered-anatomy-viewer';
    this.root.innerHTML = `
      <div class="layered-image-frame">
        <img class="anatomy-layer anatomy-layer--natural" src="${assets.natural.url}" alt="Natural ${manifest.species.commonName} educational specimen image">
        <img class="anatomy-layer anatomy-layer--external" src="${assets.external.url}" alt="External anatomy illustration of ${manifest.species.commonName}">
        <img class="anatomy-layer anatomy-layer--internal" src="${assets.internal.url}" alt="Internal anatomy educational illustration of ${manifest.species.commonName}">
        <div class="image-hotspot-layer"></div>
        <svg class="image-incision-guide" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"><path d="M55 69 C44 72, 35 72, 26 66" pathLength="1"></path></svg>
      </div>
    `;
    container.prepend(this.root);
    this.frame = this.root.querySelector('.layered-image-frame');
    this.naturalImage = this.root.querySelector('.anatomy-layer--natural');
    this.externalImage = this.root.querySelector('.anatomy-layer--external');
    this.internalImage = this.root.querySelector('.anatomy-layer--internal');
    this.hotspotLayer = this.root.querySelector('.image-hotspot-layer');
    this.incisionGuide = this.root.querySelector('.image-incision-guide');
    this.measurement = new ImageMeasurement(this.frame, manifest, key => ({natural:this.naturalImage,external:this.externalImage,internal:this.internalImage})[key]);
    this.buildHotspots();
    this.bindInteractions();
    this.layoutObserver = new ResizeObserver(() => this.layoutHotspots());
    this.layoutObserver.observe(this.frame);
    this.setDepth(0, false);
    this.ready = Promise.all([...this.root.querySelectorAll('img')].map((image) => image.decode?.().catch(() => undefined))).then(() => this.layoutHotspots());
  }

  buildHotspots() {
    this.hotspotLayer.innerHTML = this.structures.map((item) => `
      <button class="image-hotspot" type="button" data-part-id="${item.id}" data-image-layer="${item.layer}"
        style="--hotspot-x:${item.hotspot.x}%;--hotspot-y:${item.hotspot.y}%" aria-label="Inspect ${item.name}">
        <span class="image-hotspot-dot" aria-hidden="true"></span><span class="image-hotspot-label">${item.name}</span>
      </button>
    `).join('');
  }

  bindInteractions() {
    this.handleClick = (event) => {
      const button = event.target.closest('[data-part-id]');
      if (button) this.onSelect?.(button.dataset.partId);
    };
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
    this.hotspotLayer.addEventListener('click', this.handleClick);
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
    this.incisionGuide.classList.toggle('is-visible', this.manifest.contentData.lesson.steps[this.step]?.kind === 'action-target');
    this.setDepth(this.stepDepths[this.step] ?? 0);
  }

  traceIncision() {
    this.incisionGuide.classList.add('is-visible', 'is-traced');
    this.setDepth(58);
  }

  openBodyWall() {
    this.incisionGuide.classList.remove('is-visible');
    this.setDepth(100);
  }

  setExplode(value) { this.setDepth(Number(value) * 100); }

  setLabelsVisible(visible) {
    this.labelsAll = visible;
    this.root.classList.toggle('show-image-labels', visible);
    this.updateHotspots();
  }

  setHighlighted(ids) { this.highlighted = new Set(ids); this.updateHotspots(); }

  selectPart(id) { this.selectedId = id; this.updateHotspots(); }

  layoutHotspots() {
    const key = this.layerState().key;
    const image = {natural:this.naturalImage,external:this.externalImage,internal:this.internalImage}[key];
    if (!image?.naturalWidth) return;
    const base = this.frame.getBoundingClientRect();
    if (!(base.width>0 && base.height>0)) return;
    const rect = fittedImageRect(image.naturalWidth,image.naturalHeight,base);
    const left = rect.left-base.left, top=rect.top-base.top;
    this.hotspotLayer.querySelectorAll('[data-part-id]').forEach(button => {
      const item=this.anatomy[button.dataset.partId];
      button.style.setProperty('--hotspot-x',(left+item.hotspot.x/100*rect.width)+'px');
      button.style.setProperty('--hotspot-y',(top+item.hotspot.y/100*rect.height)+'px');
    });
    Object.assign(this.incisionGuide.style,{inset:'auto',left:left+'px',top:top+'px',width:rect.width+'px',height:rect.height+'px'});
  }

  updateHotspots() {
    this.layoutHotspots();
    const state = this.layerState();
    this.hotspotLayer.querySelectorAll('[data-part-id]').forEach((button) => {
      const id = button.dataset.partId;
      const visible = state.key === button.dataset.imageLayer;
      const relevant = this.labelsAll || this.highlighted.has(id) || this.selectedId === id;
      button.classList.toggle('is-layer-visible', visible && relevant);
      button.classList.toggle('is-highlighted', this.highlighted.has(id));
      button.classList.toggle('is-selected', this.selectedId === id);
      button.classList.toggle('show-label', relevant);
    });
  }

  resetView() { this.stopPlayback(); this.incisionGuide.classList.remove('is-traced'); this.setDepth(this.stepDepths[this.step] ?? 0); }

  togglePlayback() {
    if (this.reduceMotion) return false;
    if (this.playing) { this.stopPlayback(); return false; }
    this.playing = true;
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

  stopPlayback() { this.playing = false; if (this.animationFrame) cancelAnimationFrame(this.animationFrame); }

  async toggleFullscreen() {
    if (document.fullscreenElement) { await document.exitFullscreen(); return false; }
    await this.container.parentElement.requestFullscreen(); return true;
  }

  destroy() {
    this.layoutObserver?.disconnect();
    this.measurement?.destroy();
    this.stopPlayback();
    this.hotspotLayer.removeEventListener('click', this.handleClick);
    this.container.removeEventListener('wheel', this.handleWheel);
    this.container.removeEventListener('keydown', this.handleKeydown);
    this.root.remove();
  }
}
