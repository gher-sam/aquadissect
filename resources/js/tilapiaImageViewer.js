const hotspotDefinitions = [
  { id: 'eye', layer: 'external', x: 17.6, y: 48.5 },
  { id: 'operculum', layer: 'external', x: 24.5, y: 61.8 },
  { id: 'lateralLine', layer: 'external', x: 65.6, y: 50.3 },
  { id: 'dorsalFin', layer: 'external', x: 49.1, y: 24.8 },
  { id: 'caudalFin', layer: 'external', x: 90.4, y: 49.9 },
  { id: 'pectoralFin', layer: 'external', x: 38.6, y: 66.3 },
  { id: 'pelvicFin', layer: 'external', x: 45.1, y: 83.7 },
  { id: 'analFin', layer: 'external', x: 65.3, y: 77.2 },
  { id: 'vent', layer: 'external', x: 59.8, y: 67.5 },
  { id: 'gills', layer: 'internal', x: 22.1, y: 53.4 },
  { id: 'heart', layer: 'internal', x: 23.1, y: 68.4 },
  { id: 'liver', layer: 'internal', x: 29.2, y: 61.7 },
  { id: 'stomach', layer: 'internal', x: 40.1, y: 70.8 },
  { id: 'intestine', layer: 'internal', x: 49.3, y: 72.1 },
  { id: 'swimBladder', layer: 'internal', x: 56.8, y: 47.5 },
  { id: 'kidney', layer: 'internal', x: 47.5, y: 38.9 },
  { id: 'gonad', layer: 'internal', x: 55.8, y: 57.7 },
  { id: 'spine', layer: 'internal', x: 64.7, y: 38.8 },
];

const stepDepths = [0, 34, 82, 52, 100, 100, 100, 100, 100];

function clamp(value, minimum, maximum) {
  return Math.min(maximum, Math.max(minimum, value));
}

export class TilapiaImageViewer {
  constructor(container, anatomy, { onSelect, onDepthChange } = {}) {
    this.container = container;
    this.anatomy = anatomy;
    this.onSelect = onSelect;
    this.onDepthChange = onDepthChange;
    this.depth = 0;
    this.step = 0;
    this.labelsAll = false;
    this.highlighted = new Set();
    this.selectedId = null;
    this.playing = false;
    this.playDirection = 1;
    this.animationFrame = null;
    this.lastAnimationTime = null;
    this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.externalIds = hotspotDefinitions.filter((item) => item.layer === 'external').map((item) => item.id);
    this.internalIds = hotspotDefinitions.filter((item) => item.layer === 'internal').map((item) => item.id);

    this.frame = container.querySelector('#anatomyImageFrame');
    this.naturalImage = container.querySelector('#naturalTilapiaImage');
    this.externalImage = container.querySelector('#externalTilapiaImage');
    this.internalImage = container.querySelector('#internalTilapiaImage');
    this.hotspotLayer = container.querySelector('#imageHotspotLayer');
    this.incisionGuide = container.querySelector('#imageIncisionGuide');
    this.buildHotspots();
    this.bindInteractions();
    this.setDepth(0, false);
  }

  buildHotspots() {
    this.hotspotLayer.innerHTML = hotspotDefinitions.map((item) => `
      <button
        class="image-hotspot"
        type="button"
        data-part-id="${item.id}"
        data-image-layer="${item.layer}"
        style="--hotspot-x:${item.x}%;--hotspot-y:${item.y}%"
        aria-label="Inspect ${this.anatomy[item.id].name}"
      >
        <span class="image-hotspot-dot" aria-hidden="true"></span>
        <span class="image-hotspot-label">${this.anatomy[item.id].name}</span>
      </button>
    `).join('');
  }

  bindInteractions() {
    this.hotspotLayer.addEventListener('click', (event) => {
      const button = event.target.closest('[data-part-id]');
      if (!button) return;
      this.onSelect?.(button.dataset.partId);
    });

    this.container.addEventListener('wheel', (event) => {
      if (Math.abs(event.deltaY) < 1) return;
      event.preventDefault();
      this.stopPlayback();
      this.setDepth(this.depth + Math.sign(event.deltaY) * 8);
    }, { passive: false });

    this.container.addEventListener('keydown', (event) => {
      if (!['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown'].includes(event.key)) return;
      event.preventDefault();
      this.stopPlayback();
      const direction = ['ArrowDown', 'PageDown'].includes(event.key) ? 1 : -1;
      this.setDepth(this.depth + direction * 10);
    });
  }

  layerState(depth = this.depth) {
    if (depth < 22) return { name: 'Natural specimen', key: 'natural' };
    if (depth < 67) return { name: 'External anatomy', key: 'external' };
    return { name: 'Internal anatomy', key: 'internal' };
  }

  setDepth(value, announce = true) {
    this.depth = clamp(Number(value), 0, 100);
    const naturalOpacity = clamp(1 - this.depth / 28, 0, 1);
    const externalOpacity = this.depth <= 42
      ? clamp(this.depth / 24, 0, 1)
      : clamp(1 - (this.depth - 42) / 43, 0, 1);
    const internalOpacity = clamp((this.depth - 52) / 38, 0, 1);
    const scale = 1 + this.depth * 0.00055;

    this.naturalImage.style.opacity = naturalOpacity;
    this.externalImage.style.opacity = externalOpacity;
    this.internalImage.style.opacity = internalOpacity;
    this.frame.style.setProperty('--image-scale', scale.toFixed(3));
    this.updateHotspots();
    if (announce) this.onDepthChange?.(this.depth, this.layerState());
  }

  setStep(step) {
    this.step = clamp(step, 0, stepDepths.length - 1);
    this.incisionGuide.classList.toggle('is-visible', this.step === 3);
    this.setDepth(stepDepths[this.step]);
  }

  traceIncision() {
    this.incisionGuide.classList.add('is-visible', 'is-traced');
    this.setDepth(58);
  }

  openBodyWall() {
    this.incisionGuide.classList.remove('is-visible');
    this.setDepth(100);
  }

  setExplode(value) {
    this.setDepth(Number(value) * 100);
  }

  setLabelsVisible(visible) {
    this.labelsAll = visible;
    this.container.classList.toggle('show-image-labels', visible);
    this.updateHotspots();
  }

  setHighlighted(ids) {
    this.highlighted = new Set(ids);
    this.updateHotspots();
  }

  selectPart(id) {
    this.selectedId = id;
    this.updateHotspots();
  }

  updateHotspots() {
    const state = this.layerState();
    this.hotspotLayer.querySelectorAll('[data-part-id]').forEach((button) => {
      const id = button.dataset.partId;
      const layer = button.dataset.imageLayer;
      const layerVisible = state.key === layer;
      const relevant = this.labelsAll || this.highlighted.has(id) || this.selectedId === id;
      button.classList.toggle('is-layer-visible', layerVisible && relevant);
      button.classList.toggle('is-highlighted', this.highlighted.has(id));
      button.classList.toggle('is-selected', this.selectedId === id);
      button.classList.toggle('show-label', this.labelsAll || this.highlighted.has(id) || this.selectedId === id);
    });
  }

  resetView() {
    this.stopPlayback();
    this.incisionGuide.classList.remove('is-traced');
    this.setDepth(stepDepths[this.step]);
  }

  togglePlayback() {
    if (this.reduceMotion) return false;
    if (this.playing) {
      this.stopPlayback();
      return false;
    }
    this.playing = true;
    this.lastAnimationTime = null;
    const animate = (timestamp) => {
      if (!this.playing) return;
      if (this.lastAnimationTime != null) {
        const elapsed = Math.min(60, timestamp - this.lastAnimationTime);
        const next = this.depth + this.playDirection * elapsed * 0.025;
        if (next >= 100 || next <= 0) this.playDirection *= -1;
        this.setDepth(clamp(next, 0, 100));
      }
      this.lastAnimationTime = timestamp;
      this.animationFrame = requestAnimationFrame(animate);
    };
    this.animationFrame = requestAnimationFrame(animate);
    return true;
  }

  stopPlayback() {
    this.playing = false;
    this.lastAnimationTime = null;
    if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
    this.animationFrame = null;
  }
}
