import { LayeredAnatomyViewer } from './layeredAnatomyViewer.js';

export function supportsThreeDimensions(manifest) {
  return Boolean(manifest.viewer?.optional3d?.adapter || manifest.assetRecords?.model);
}

export function supportsVideo(manifest) {
  return Boolean(manifest.assetRecords?.video);
}

export async function createViewer(type, container, anatomy, manifest, callbacks) {
  if (type === 'video') {
    const { SpeciesVideoViewer } = await import('./speciesVideoViewer.js');
    return new SpeciesVideoViewer(container, anatomy, manifest, callbacks);
  }

  if (type === '2d') {
    return new LayeredAnatomyViewer(container, anatomy, manifest, callbacks);
  }

  if (manifest.viewer?.optional3d?.adapter === 'tilapia-procedural') {
    const { Tilapia3DViewer } = await import('./tilapia3DViewer.js');
    return new Tilapia3DViewer(container, anatomy, callbacks);
  }

  if (manifest.assetRecords?.model) {
    const { GenericGlbViewer } = await import('./genericGlbViewer.js');
    return new GenericGlbViewer(container, anatomy, manifest, callbacks);
  }

  throw new Error('This package does not provide the selected viewer.');
}
