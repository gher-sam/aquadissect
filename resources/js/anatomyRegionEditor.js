import { escapeHtml as e } from './portal/format.js';
import { fittedImageRect } from './imageMeasurementData.js';
import { regionMapFor, validPolygon, safeAnatomySource } from './anatomyRegions.js';

const clone = value => JSON.parse(JSON.stringify(value));
export function mountAnatomyRegionEditor(host, manifest) {
  const anatomy = clone(manifest.contentData.anatomy), structures = anatomy.structures;
  const maps = {}, original = {}; let current = structures[0], points = [], edited = false, disposed = false, loading = true, choiceVersion = 0;
  for (const layer of ['external', 'internal']) {
    const map = regionMapFor(manifest, layer);
    if (map) maps[layer] = { ...clone(map), parts: clone({ ...map.parts }), unmapped: clone({ ...map.unmapped }) };
  }
  host.innerHTML = `<div class="region-editor"><p>Trace the visible boundary of each structure. Use the image itself and the anatomy references. Mark a part as not outlined when its boundary cannot be established. Saving creates a new draft for independent faculty review.</p><label>Structure<select data-region-pick>${structures.map(p => `<option value="${e(p.id)}">${e(p.name)} · ${e(p.layer)}</option>`).join('')}</select></label><div class="region-editor-stage"><img alt="Anatomy image for tracing"><svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true"></svg></div><p data-region-status role="status"></p><div class="region-editor-controls"><button type="button" data-region-undo>Undo point</button><button type="button" data-region-finish>Finish outline</button><button type="button" data-region-discard>Discard unfinished points</button><button type="button" data-region-reset>Reset this part</button></div><fieldset><legend>Add a point using coordinates</legend><label>X (%)<input data-region-x type="number" min="0" max="100" step="0.1"></label><label>Y (%)<input data-region-y type="number" min="0" max="100" step="0.1"></label><button type="button" data-region-coordinate>Add coordinate</button></fieldset><label>Reason when a part cannot be outlined<input data-region-reason minlength="10" maxlength="1000" placeholder="For example: the boundary is not visible in this image"></label><button type="button" data-region-unmapped>Mark this part as not outlined</button><label>Image basis<select data-region-basis><option value="illustration">Illustration</option><option value="photograph">Photograph</option></select></label><label>Anatomy references for this image<textarea data-region-sources rows="4" placeholder="Reference title | https://official-source.example/page"></textarea></label><small>One reference per line: title, a vertical bar, and its HTTPS address. These references support anatomical interpretation; they do not record faculty approval.</small></div>`;
  const image = host.querySelector('img'), stage = host.querySelector('.region-editor-stage'), svg = host.querySelector('svg'), picker = host.querySelector('[data-region-pick]'), status = host.querySelector('[data-region-status]');
  const field = key => host.querySelector(`[data-region-${key}]`);
  const images = {};
  function ensureMap(layer) {
    if (!maps[layer]) maps[layer] = { layer, imageSha256: manifest.assetRecords[layer].sha256, width: images[layer]?.naturalWidth, height: images[layer]?.naturalHeight, basis: 'illustration', sources: [], parts: {}, unmapped: Object.fromEntries(structures.filter(p => p.layer === layer).map(p => [p.id, 'An image-matched outline has not yet been supplied.'])) };
    return maps[layer];
  }
  function saveReferenceFields() {
    const map = ensureMap(current.layer);
    map.basis = field('basis').value;
    map.sources = field('sources').value.split('\n').filter(s => s.trim()).map(line => { const separator = line.indexOf('|'), title = line.slice(0, separator).trim(), url = separator < 0 ? '' : line.slice(separator + 1).trim(); const retained = map.sources.find(source => source.title === title && source.url === url); return { ...retained, title, url }; });
  }
  function draw() {
    const map = ensureMap(current.layer), shape = map.parts[current.id]; svg.replaceChildren();
    const add = (tag, attributes) => { const node = document.createElementNS('http://www.w3.org/2000/svg', tag); for (const [key, value] of Object.entries(attributes)) node.setAttribute(key, value); svg.append(node); };
    for (const polygon of shape?.polygons || []) add('polygon', { points: polygon.map(p => p.join(',')).join(' ') });
    if (points.length) { add('polyline', { points: points.map(p => p.join(',')).join(' ') }); points.forEach(p => add('circle', { cx: p[0], cy: p[1], r: .45 })); }
    picker.disabled = loading || points.length > 0;
    svg.style.visibility = loading ? 'hidden' : 'visible'; field('coordinate').disabled = loading;
    field('finish').disabled = loading || points.length < 3; field('undo').disabled = !points.length;
    field('discard').disabled = !points.length;
    status.textContent = loading ? 'Loading the exact source image…' : `${current.name}: ${shape?.polygons.length || 0} saved outline(s); ${points.length} unfinished point(s).`;
    const base = stage.getBoundingClientRect();
    if (image.naturalWidth && base.width) {
      const rect = fittedImageRect(image.naturalWidth, image.naturalHeight, base);
      Object.assign(svg.style, { left: `${rect.left - base.left}px`, top: `${rect.top - base.top}px`, width: `${rect.width}px`, height: `${rect.height}px` });
    }
  }
  async function choose() {
    const generation = ++choiceVersion; loading = true; picker.disabled = true;
    current = structures.find(p => p.id === picker.value) || structures[0];
    image.src = manifest.assetRecords[current.layer].url; image.alt = `${current.name}: trace the visible boundary on the ${current.layer} image`;
    try { await image.decode(); } catch { status.textContent = 'Image unavailable. Reconnect before tracing this image.'; return; }
    if (disposed || generation !== choiceVersion) return;
    loading = false;
    images[current.layer] = image;
    const map = ensureMap(current.layer); map.width = image.naturalWidth; map.height = image.naturalHeight;
    if (!original[current.layer]) original[current.layer] = clone(map);
    field('basis').value = map.basis; field('reason').value = map.unmapped[current.id] || '';
    field('sources').value = map.sources.map(s => `${s.title} | ${s.url}`).join('\n'); draw();
  }
  function addPoint(x, y) {
    if (loading || !image.naturalWidth || !Number.isFinite(x) || !Number.isFinite(y) || x < 0 || x > 100 || y < 0 || y > 100 || points.length >= 200) return;
    points.push([Math.round(x * 1000) / 1000, Math.round(y * 1000) / 1000]); edited = true; draw();
  }
  stage.addEventListener('click', event => {
    if (!image.naturalWidth) return;
    const rect = fittedImageRect(image.naturalWidth, image.naturalHeight, stage.getBoundingClientRect());
    addPoint((event.clientX - rect.left) / rect.width * 100, (event.clientY - rect.top) / rect.height * 100);
  });
  field('coordinate').onclick = () => { if (field('x').value !== '' && field('y').value !== '') addPoint(Number(field('x').value), Number(field('y').value)); };
  field('undo').onclick = () => { points.pop(); draw(); };
  field('discard').onclick = () => { points = []; draw(); };
  field('finish').onclick = () => {
    if (!validPolygon(points)) { status.textContent = 'The outline crosses itself, repeats a point, or has no area. Undo a point and trace around the boundary.'; return; }
    const map = ensureMap(current.layer), previous = map.parts[current.id]?.polygons || [];
    if (previous.length >= 20) { status.textContent = 'This part already has 20 outlines. Reset it before adding more.'; return; }
    map.parts[current.id] = { label: current.name, polygons: [...previous, points] }; delete map.unmapped[current.id]; points = []; edited = true; draw();
  };
  field('reset').onclick = () => { const map = ensureMap(current.layer), base = original[current.layer]; if (base?.parts[current.id]) map.parts[current.id] = clone(base.parts[current.id]); else delete map.parts[current.id]; if (base?.unmapped[current.id]) map.unmapped[current.id] = base.unmapped[current.id]; else delete map.unmapped[current.id]; field('reason').value = map.unmapped[current.id] || ''; points = []; edited = true; draw(); };
  field('unmapped').onclick = () => { const reason = field('reason').value.trim(); if (reason.length < 10) { status.textContent = 'Explain why the boundary cannot be established (at least 10 characters).'; return; } const map = ensureMap(current.layer); delete map.parts[current.id]; map.unmapped[current.id] = reason; points = []; edited = true; draw(); };
  field('sources').addEventListener('input', () => { edited = true; }); field('basis').addEventListener('change', () => { edited = true; });
  picker.onchange = () => { saveReferenceFields(); void choose(); };
  const observer = new ResizeObserver(draw); observer.observe(stage); void choose();
  return {
    get changed() { return edited; },
    async value() {
      if (loading) throw new Error('Wait for the source image to load before saving.');
      if (points.length) throw new Error('Finish or discard the unfinished outline before saving.');
      saveReferenceFields();
      for (const [layer, map] of Object.entries(maps)) {
        if (map.imageSha256 !== manifest.assetRecords[layer].sha256) throw new Error('The image changed. Reopen the draft before tracing it.');
        if (Object.keys(map.parts).length && (!map.sources.length || map.sources.some(s => !safeAnatomySource(s)))) throw new Error('Every outlined image requires a reference title and a valid HTTPS source.');
        if (!map.width || !map.height) throw new Error('Wait for the source image to load before saving.');
      }
      anatomy.regionMaps = clone(maps); return anatomy;
    },
    destroy() { disposed = true; observer.disconnect(); },
  };
}
