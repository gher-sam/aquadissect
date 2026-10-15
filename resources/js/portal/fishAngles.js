import { escapeHtml } from './format.js';

// Regions in the supplied sheet, normalized to its displayed 2048 x 1143 grid.
export const fishAngles = [
  ['Left profile', 60, 30, 625, 315],
  ['Right profile', 695, 30, 610, 315],
  ['Front head-on', 1410, 30, 165, 315],
  ['Rear tail-on', 1710, 30, 200, 315],
  ['Dorsal', 60, 460, 625, 215],
  ['Ventral', 720, 425, 605, 280],
  ['Front-left three-quarter', 45, 780, 445, 320],
  ['Front-right three-quarter', 550, 780, 450, 320],
  ['Rear-left three-quarter', 1070, 780, 445, 320],
  ['Angled upward', 1560, 780, 440, 320],
];

export function fishCarousel(item) {
  const url = item.cardPreviewVideoUrl + (item.cardPreviewVideoUrl.includes('?') ? '&' : '?');
  return `<div class="fish-carousel" data-fish-carousel data-angle-src="${escapeHtml(url + 'format=angles')}" role="region" aria-roledescription="carousel" aria-label="Rabbitfish viewing angles" tabindex="0">
    <div class="fish-carousel-stage">
      <video class="species-card-video" data-card-video muted loop playsinline preload="metadata" poster="${escapeHtml(item.naturalImageUrl || '')}" aria-label="${escapeHtml(item.commonName)} swimming preview"><source src="${escapeHtml(url + 'format=cutout')}" type="video/webm"></video>
      <canvas data-fish-angle hidden role="img" aria-label="Rabbitfish angle"></canvas>
    </div>
    <div class="fish-carousel-controls"><button type="button" data-angle-step="-1" aria-label="Previous fish view">‹</button><span data-angle-caption aria-live="polite" aria-atomic="true">Swimming · 1 / 11</span><button type="button" data-angle-step="1" aria-label="Next fish view">›</button></div>
    <label class="fish-angle-picker">View<select data-angle-select><option value="0">Swimming</option>${fishAngles.map(([label], index) => `<option value="${index + 1}">${label}</option>`).join('')}</select></label>
    <button type="button" class="species-card-motion" data-card-motion aria-label="Play fish animation">Play</button>
    <small class="species-card-video-note">Preview · awaiting faculty review</small>
  </div>`;
}

export function setupFishCarousels(panel) {
  panel?.querySelectorAll('[data-fish-carousel]').forEach((carousel) => {
    const video = carousel.querySelector('video');
    const canvas = carousel.querySelector('canvas');
    const select = carousel.querySelector('select');
    const caption = carousel.querySelector('[data-angle-caption]');
    const motionButton = carousel.querySelector('[data-card-motion]');
    const sheet = new Image();
    let current = 0;
    let loaded = false;
    let failed = false;
    const views = new Map();
    function draw() {
      if (!current || !loaded) return;
      const [label, x, y, width, height] = fishAngles[current - 1];
      if (!views.has(current)) {
        const scale = sheet.naturalWidth / 2048;
        const buffer = document.createElement('canvas');
        buffer.width = Math.round(width * scale);
        buffer.height = Math.round(height * scale);
        const context = buffer.getContext('2d', { willReadFrequently: true });
        context.drawImage(sheet, x * scale, y * scale, width * scale, height * scale, 0, 0, buffer.width, buffer.height);
        // Key the supplied green backdrop at display time; retain the original source.
        const pixels = context.getImageData(0, 0, buffer.width, buffer.height);
        for (let i = 0; i < pixels.data.length; i += 4) {
          const green = pixels.data[i + 1];
          const other = Math.max(pixels.data[i], pixels.data[i + 2]);
          const excess = green - other;
          if (excess > 35) {
            pixels.data[i + 3] = Math.round(255 * Math.max(0, 1 - (excess - 35) / 65));
            pixels.data[i + 1] = Math.min(green, other + 25);
          }
        }
        context.putImageData(pixels, 0, 0);
        views.set(current, buffer);
      }
      const buffer = views.get(current);
      canvas.width = buffer.width;
      canvas.height = buffer.height;
      canvas.getContext('2d').drawImage(buffer, 0, 0);
      canvas.setAttribute('aria-label', `Rabbitfish: ${label}`);
    }
    function show(index) {
      current = (index + 11) % 11;
      select.value = String(current);
      const fallback = carousel.querySelector('[data-card-fallback]');
      video.hidden = current !== 0 || Boolean(fallback);
      if (fallback) fallback.hidden = current !== 0;
      canvas.hidden = current === 0;
      motionButton.hidden = current !== 0 || Boolean(fallback);
      caption.textContent = `${current ? fishAngles[current - 1][0] : 'Swimming'} · ${current + 1} / 11${current && failed ? ' · unavailable' : current && !loaded ? ' · loading' : ''}`;
      if (current) video.pause();
      else if (!fallback && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) video.play().catch(() => {});
      draw();
    }
    sheet.onload = () => { loaded = true; if (current) show(current); };
    sheet.onerror = () => { failed = true; if (current) show(current); };
    sheet.src = carousel.dataset.angleSrc;
    carousel.querySelectorAll('[data-angle-step]').forEach(button => button.addEventListener('click', () => show(current + Number(button.dataset.angleStep))));
    select.addEventListener('change', () => show(Number(select.value)));
    carousel.addEventListener('keydown', event => {
      if (event.target.tagName === 'SELECT') return;
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      show(event.key === 'Home' ? 0 : event.key === 'End' ? 10 : current + (event.key === 'ArrowRight' ? 1 : -1));
    });
  });
}
