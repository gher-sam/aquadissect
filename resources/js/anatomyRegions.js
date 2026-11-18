import { builtInRegionMaps } from './data/anatomyRegionMaps.js';

export function polygonArea(points) {
  return Math.abs(points.reduce((sum, p, i) => { const q = points[(i + 1) % points.length]; return sum + p[0] * q[1] - q[0] * p[1]; }, 0)) / 2;
}

const cross = (a, b, c) => (b[0] - a[0]) * (c[1] - a[1]) - (b[1] - a[1]) * (c[0] - a[0]);
const between = (p, a, b) => p[0] >= Math.min(a[0], b[0]) - 1e-8 && p[0] <= Math.max(a[0], b[0]) + 1e-8 && p[1] >= Math.min(a[1], b[1]) - 1e-8 && p[1] <= Math.max(a[1], b[1]) + 1e-8;
function intersects(a, b, c, d) {
  const x = cross(a, b, c), y = cross(a, b, d), z = cross(c, d, a), w = cross(c, d, b);
  return (x * y < -1e-12 && z * w < -1e-12) || (Math.abs(x) < 1e-8 && between(c, a, b)) || (Math.abs(y) < 1e-8 && between(d, a, b)) || (Math.abs(z) < 1e-8 && between(a, c, d)) || (Math.abs(w) < 1e-8 && between(b, c, d));
}

export function validPolygon(points) {
  if (!Array.isArray(points) || points.length < 3 || points.length > 200 || points.some(p => !Array.isArray(p) || p.length !== 2 || p.some(n => typeof n !== 'number' || !Number.isFinite(n) || n < 0 || n > 100))) return false;
  if (polygonArea(points) < .001) return false;
  for (let i = 0; i < points.length; i++) {
    const next = (i + 1) % points.length;
    if (Math.hypot(points[i][0] - points[next][0], points[i][1] - points[next][1]) < 1e-6) return false;
    for (let j = i + 1; j < points.length; j++) {
      if (j === next || (j + 1) % points.length === i) continue;
      if (intersects(points[i], points[next], points[j], points[(j + 1) % points.length])) return false;
    }
  }
  return true;
}

export function polygonPath(points) {
  if (!validPolygon(points)) return '';
  return points.map((p, i) => `${i ? 'L' : 'M'}${p[0]} ${p[1]}`).join(' ') + ' Z';
}

export function safeAnatomySource(source) {
  try { const url = new URL(source?.url); return url.protocol === 'https:' && !url.username && !url.password && typeof source.title === 'string' && source.title.trim() ? { ...source, url: url.href } : null; } catch { return null; }
}

export function sourceAppliesTo(source, part) {
  if (!Object.hasOwn(source, 'appliesTo')) return true;
  const scope = source.appliesTo;
  return Array.isArray(scope) && scope.some(target => target?.id === part.id && target?.label === part.name);
}

export function regionMapFor(manifest, layer) {
  const image = manifest.assetRecords?.[layer];
  const supplied = manifest.contentData?.anatomy?.regionMaps?.[layer];
  const map = supplied || builtInRegionMaps[image?.sha256];
  if (!image?.sha256 || !map || map.layer !== layer || map.imageSha256 !== image.sha256 || (!supplied && map.speciesSlug !== manifest.species?.slug)) return null;
  if (!supplied && Array.isArray(manifest.contentData?.anatomy?.structures)) {
    const parts = manifest.contentData.anatomy.structures.filter(p => p.layer === layer);
    const sources = map.sources.flatMap(source => {
      if (!Object.hasOwn(source, 'appliesTo')) return [source];
      const appliesTo = parts.filter(p => sourceAppliesTo(source, p)).map(p => ({ id:p.id, label:p.name }));
      return appliesTo.length ? [{ ...source, appliesTo }] : [];
    });
    return { ...map, sources, parts: Object.fromEntries(parts.filter(p => map.parts[p.id]?.label === p.name).map(p => [p.id, map.parts[p.id]])),
      unmapped: Object.fromEntries(parts.filter(p => map.parts[p.id]?.label !== p.name).map(p => [p.id, map.unmapped[p.id] || 'An image-matched outline has not yet been supplied for this structure.'])) };
  }
  return map;
}

export function regionFor(manifest, part) {
  const map = regionMapFor(manifest, part.layer), shape = map?.parts?.[part.id];
  const sources = (Array.isArray(map?.sources) ? map.sources : []).map(safeAnatomySource).filter(Boolean).filter(source => sourceAppliesTo(source, part));
  const valid = shape?.label === part.name && Array.isArray(shape.polygons) && shape.polygons.length > 0 && shape.polygons.length <= 20 && shape.polygons.every(validPolygon) && sources.length;
  return valid ? { available: true, map, polygons: shape.polygons, sources, basis: map.basis }
    : { available: false, map, sources, reason: map?.unmapped?.[part.id] || 'An image-matched outline is not available for this structure. Use the structure list and reference material.' };
}
