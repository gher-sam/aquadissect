import { regionFor, polygonPath } from './anatomyRegions.js';
import { fittedImageRect } from './imageMeasurementData.js';

const NS = 'http://www.w3.org/2000/svg';
export class AnatomyRegionOverlay {
  constructor(frame, manifest, structures, onSelect) {
    this.frame = frame; this.manifest = manifest; this.structures = structures;
    this.root = document.createElement('div'); this.root.className = 'anatomy-region-overlay';
    this.svg = document.createElementNS(NS, 'svg'); this.svg.setAttribute('viewBox', '0 0 100 100'); this.svg.setAttribute('preserveAspectRatio', 'none'); this.svg.setAttribute('aria-label', 'Anatomical structure regions');
    this.caption = document.createElement('p'); this.caption.className = 'anatomy-region-caption'; this.caption.setAttribute('aria-live', 'polite'); this.caption.hidden = true;
    this.root.append(this.svg); frame.append(this.root, this.caption); this.nodes = new Map();
    structures.forEach(part => {
      const region = regionFor(manifest, part); if (!region.available) return;
      const group = document.createElementNS(NS, 'g'); group.dataset.regionPart = part.id; group.dataset.imageLayer = part.layer;
      group.setAttribute('role', 'button'); group.setAttribute('tabindex', '-1'); group.setAttribute('aria-label', `Inspect ${part.name}`); group.setAttribute('aria-pressed', 'false');
      const title = document.createElementNS(NS, 'title'); title.textContent = part.name; group.append(title);
      for (const polygon of region.polygons) {
        for (const className of ['anatomy-region-halo', 'anatomy-region-shape']) {
          const path = document.createElementNS(NS, 'path'); path.setAttribute('d', polygonPath(polygon)); path.setAttribute('vector-effect', 'non-scaling-stroke'); path.setAttribute('class', className); group.append(path);
        }
      }
      this.svg.append(group); this.nodes.set(part.id, { group, region, part });
    });
    this.click = event => { const group = event.target.closest('[data-region-part]'); if (group && !group.classList.contains('is-layer-hidden')) onSelect?.(group.dataset.regionPart); };
    this.key = event => { if (!['Enter', ' '].includes(event.key) || !event.target.matches('[data-region-part]')) return; event.preventDefault(); onSelect?.(event.target.dataset.regionPart); };
    this.svg.addEventListener('click', this.click); this.svg.addEventListener('keydown', this.key);
  }

  update(image, layer, selectedId, targets, showAll) {
    this.layer = layer;
    this.imageWidth = image?.naturalWidth; this.imageHeight = image?.naturalHeight;
    if (image?.naturalWidth && image?.naturalHeight) {
      const base = this.frame.getBoundingClientRect();
      if (base.width > 0 && base.height > 0) {
        const rect = fittedImageRect(image.naturalWidth, image.naturalHeight, base);
        Object.assign(this.root.style, { left: `${rect.left - base.left}px`, top: `${rect.top - base.top}px`, width: `${rect.width}px`, height: `${rect.height}px` });
      }
    }
    this.nodes.forEach(({ group, region, part }) => {
      const visible = part.layer === layer && image?.naturalWidth === region.map.width && image?.naturalHeight === region.map.height;
      group.classList.toggle('is-layer-hidden', !visible); group.classList.toggle('is-selected', part.id === selectedId); group.classList.toggle('is-target', targets.has(part.id)); group.classList.toggle('show-region', showAll);
      group.setAttribute('tabindex', visible ? '0' : '-1'); group.setAttribute('aria-hidden', String(!visible)); group.setAttribute('aria-pressed', String(part.id === selectedId));
    });
    const part = this.structures.find(p => p.id === selectedId);
    this.caption.hidden = !part;
    if (part) {
      const status = this.status(part.id);
      this.caption.textContent = !status.available ? `${part.name} · Outline not available. Use the structure list.`
        : part.layer !== layer ? `${part.name} · Open the ${part.layer} layer to see its outline.` : `${part.name} · ${status.basis === 'photograph' ? 'Photograph' : 'Illustration'} outline`;
    }
  }

  status(id) {
    const part = this.structures.find(p => p.id === id);
    const region = part ? regionFor(this.manifest, part) : { available: false, sources: [] };
    if (region.available && part.layer === this.layer && (this.imageWidth !== region.map.width || this.imageHeight !== region.map.height)) return { ...region, available: false, reason: 'The matching source image has not loaded. Reconnect or use the structure list.' };
    return region;
  }
  snapshot() { return { layer: this.layer, selected: [...this.nodes.values()].filter(n => n.group.classList.contains('is-selected') && !n.group.classList.contains('is-layer-hidden')).map(n => n.part.id), outlinedParts: [...this.nodes.values()].filter(n => !n.group.classList.contains('is-layer-hidden')).map(n => n.part.id), coordinateSystem: 'Percent of the exact source image, origin at its top-left; layout follows object-fit contain.' }; }
  destroy() { this.svg.removeEventListener('click', this.click); this.svg.removeEventListener('keydown', this.key); this.root.remove(); this.caption.remove(); }
}
