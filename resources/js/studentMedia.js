import '../css/student-media.css';

const roots = '#app, .species-card-image, .safety-experience, .fish-details-dialog';
const players = new Map();

function clock(value) {
  const seconds = Math.max(0, Math.floor(Number(value) || 0));
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

function player(video) {
  if (!video.controls || players.has(video)) return;
  const wrapper = document.createElement('div');
  wrapper.className = 'student-media-player';
  video.before(wrapper); wrapper.append(video);
  const controls = document.createElement('div');
  controls.className = 'student-media-controls';
  controls.setAttribute('role', 'group'); controls.setAttribute('aria-label', 'Video playback');
  controls.innerHTML = '<button type="button" data-play>Play</button><button type="button" data-replay>Replay</button><label class="student-media-seek">Seek video<input type="range" min="0" max="1000" value="0" step="1" aria-label="Seek video"></label><output aria-label="Video time">0:00 / 0:00</output><button type="button" data-mute>Mute</button><button type="button" data-captions hidden aria-pressed="false">Captions</button><button type="button" data-fullscreen>Fullscreen</button><span role="status" class="student-media-status"></span>';
  wrapper.append(controls);
  video.controls = false;
  video.setAttribute('controlslist', 'nodownload noremoteplayback');
  const controller = new AbortController();
  const options = { signal: controller.signal };
  const seek = controls.querySelector('input');
  const status = controls.querySelector('[role="status"]');
  const play = controls.querySelector('[data-play]');
  const mute = controls.querySelector('[data-mute]');
  const captions = controls.querySelector('[data-captions]');
  const sync = () => {
    wrapper.hidden = video.hidden;
    play.textContent = video.paused ? 'Play' : 'Pause';
    play.setAttribute('aria-label', `${video.paused ? 'Play' : 'Pause'} ${video.getAttribute('aria-label') || 'video'}`);
    mute.textContent = video.muted ? 'Unmute' : 'Mute';
    mute.setAttribute('aria-pressed', String(video.muted));
    const duration = Number.isFinite(video.duration) ? video.duration : 0;
    seek.disabled = duration <= 0;
    seek.value = duration ? String(Math.round(video.currentTime / duration * 1000)) : '0';
    seek.setAttribute('aria-valuetext', `${clock(video.currentTime)} of ${clock(duration)}`);
    controls.querySelector('output').textContent = `${clock(video.currentTime)} / ${clock(duration)}`;
    captions.hidden = video.textTracks.length === 0;
    status.textContent = video.error ? 'Video unavailable. Reconnect to verify or prepare this lesson again.' : '';
  };
  play.addEventListener('click', () => { if (video.paused) video.play().catch(() => { status.textContent = 'Playback could not start. Check that this lesson is prepared.'; }); else video.pause(); }, options);
  controls.querySelector('[data-replay]').addEventListener('click', () => { video.currentTime = 0; video.play().catch(() => undefined); }, options);
  seek.addEventListener('input', () => { if (Number.isFinite(video.duration) && video.duration > 0) video.currentTime = Number(seek.value) / 1000 * video.duration; sync(); }, options);
  mute.addEventListener('click', () => { video.muted = !video.muted; sync(); }, options);
  captions.addEventListener('click', () => {
    const enabled = captions.getAttribute('aria-pressed') !== 'true';
    for (let index = 0; index < video.textTracks.length; index += 1) video.textTracks[index].mode = enabled && index === 0 ? 'showing' : 'disabled';
    captions.setAttribute('aria-pressed', String(enabled));
  }, options);
  const fullscreen = controls.querySelector('[data-fullscreen]');
  fullscreen.hidden = !wrapper.requestFullscreen;
  fullscreen.addEventListener('click', () => { const result = document.fullscreenElement ? document.exitFullscreen() : wrapper.requestFullscreen(); result?.catch(() => { status.textContent = 'Fullscreen is unavailable in this browser.'; }); }, options);
  for (const event of ['play', 'pause', 'timeupdate', 'durationchange', 'loadedmetadata', 'volumechange', 'emptied', 'error']) video.addEventListener(event, sync, options);
  video.textTracks.addEventListener?.('addtrack', sync, options);
  const observer = new MutationObserver(sync);
  observer.observe(video, { attributes: true, attributeFilter: ['hidden', 'src', 'aria-label'] });
  players.set(video, { wrapper, destroy() { controller.abort(); observer.disconnect(); video.controls = true; wrapper.before(video); wrapper.remove(); } });
  sync();
}

export function closeStudentMedia() {
  for (const video of document.querySelectorAll('video')) {
    if (!video.closest(roots)) continue;
    video.pause(); video.removeAttribute('src');
    for (const source of video.querySelectorAll('source')) source.removeAttribute('src');
    video.load();
  }
  for (const dialog of document.querySelectorAll('#app dialog[open], .enhancement-dialog[open]')) dialog.close();
}

export function setupStudentMedia(currentUser) {
  let scheduled = false;
  const refresh = () => {
    scheduled = false;
    const enabled = currentUser()?.role === 'student';
    document.body.classList.toggle('student-media-view', enabled);
    if (enabled) {
      document.querySelectorAll('img,video,canvas').forEach(media => {
        if (!media.closest(roots)) return;
        media.draggable = false;
        if (media instanceof HTMLVideoElement) player(media);
      });
    }
    for (const [video, instance] of players) {
      if (!enabled || !video.isConnected) { if (video.isConnected) instance.destroy(); players.delete(video); }
    }
  };
  const schedule = () => { if (!scheduled) { scheduled = true; queueMicrotask(refresh); } };
  const blockSave = event => {
    if (currentUser()?.role !== 'student' || !(event.target instanceof Element)) return;
    const media = event.target.closest('img,video,canvas,svg');
    if (media?.closest(roots) && !media.closest('button,a')) event.preventDefault();
  };
  document.addEventListener('contextmenu', blockSave);
  document.addEventListener('dragstart', blockSave);
  window.addEventListener('aquadissect-user-change', schedule);
  new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
  refresh();
}
