export class SpeciesVideoViewer {
  constructor(container, anatomy, manifest, { onPlaybackChange } = {}) {
    this.container = container;
    this.manifest = manifest;
    this.playing = false;
    this.externalIds = Object.values(anatomy).filter((item) => item.layer === 'external').map((item) => item.id);
    this.internalIds = Object.values(anatomy).filter((item) => item.layer === 'internal').map((item) => item.id);
    this.reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    this.saveData = Boolean(navigator.connection?.saveData);
    this.onPlaybackChange = onPlaybackChange;

    const videoAsset = manifest.assetRecords?.video;
    if (!videoAsset?.url) throw new Error('This species package does not include a swimming video.');

    this.root = document.createElement('div');
    this.root.className = 'species-video-viewer';
    this.video = document.createElement('video');
    this.video.className = 'species-swimming-video';
    this.video.muted = true;
    this.video.defaultMuted = true;
    this.video.loop = true;
    this.video.controls = false;
    this.video.disablePictureInPicture = true;
    this.video.preload = 'metadata';
    this.video.setAttribute('playsinline', '');
    this.video.setAttribute('controlslist', 'nodownload noplaybackrate noremoteplayback');
    this.video.setAttribute('aria-label', `${manifest.species.commonName} swimming freely in a fixed side view`);
    if (manifest.assetRecords?.natural?.url) this.video.poster = manifest.assetRecords.natural.url;

    const source = document.createElement('source');
    source.src = videoAsset.url;
    source.type = videoAsset.mimeType || 'video/mp4';
    this.video.append(source, document.createTextNode('Your browser does not support this educational specimen video.'));
    this.root.append(this.video);
    container.prepend(this.root);

    this.handlePlay = () => { this.playing = true; this.onPlaybackChange?.(true); };
    this.handlePause = () => { this.playing = false; this.onPlaybackChange?.(false); };
    this.video.addEventListener('play', this.handlePlay);
    this.video.addEventListener('pause', this.handlePause);

    this.ready = this.video.readyState >= HTMLMediaElement.HAVE_METADATA
      ? Promise.resolve()
      : new Promise((resolve, reject) => {
        this.video.addEventListener('loadedmetadata', resolve, { once: true });
        this.video.addEventListener('error', () => reject(new Error('The swimming video could not be loaded.')), { once: true });
      });

    if (!this.reduceMotion && !this.saveData) {
      this.video.autoplay = true;
      this.video.play().catch(() => undefined);
    }
  }

  setStep() {}

  setHighlighted() {}

  setLabelsVisible() {}

  selectPart() {}

  traceIncision() {}

  openBodyWall() {}

  setExplode() {}

  resetView() {
    this.video.currentTime = 0;
  }

  togglePlayback() {
    if (this.video.paused) {
      this.video.play().catch(() => undefined);
      return true;
    }
    this.video.pause();
    return false;
  }

  stopPlayback() {
    this.video.pause();
  }

  async toggleFullscreen() {
    if (document.fullscreenElement) {
      await document.exitFullscreen();
      return false;
    }
    await this.container.parentElement.requestFullscreen();
    return true;
  }

  destroy() {
    this.stopPlayback();
    this.video.removeEventListener('play', this.handlePlay);
    this.video.removeEventListener('pause', this.handlePause);
    this.root.remove();
  }
}
