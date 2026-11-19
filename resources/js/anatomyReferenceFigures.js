import { escapeHtml as e } from './portal/format.js';
import { sourceAppliesTo } from './anatomyRegions.js';

const figures = Object.freeze({
  'guttatus-kidney-histology-2026': Object.freeze({
    id: 'guttatus-kidney-histology-2026',
    species: 'Siganus guttatus',
    partName: 'Kidney',
    layer: 'internal',
    sourceUrl: 'https://doi.org/10.1155/tbed/2656593',
    imageUrl: new URL('../media/references/guttatus-histopathology-2026-figure5.jpg', import.meta.url).href,
    imageSha256: '6796b68db074ec0917aef5b8957901399f7614fb6d59c2a7f414fd495aa31ca7',
    title: 'Published kidney tissue reference',
    caption: 'Kidney tissue is shown in panel C of Figure 5. The other panels show different organs.',
    limitation: 'Microscope view of diseased Siganus guttatus tissue. This is not a normal whole-kidney outline or a location map for the fish illustration.',
    credit: 'Hoai et al. (2026), Transboundary and Emerging Diseases. Original figure reproduced unchanged.',
    licenseName: 'CC BY 4.0',
    licenseUrl: 'https://creativecommons.org/licenses/by/4.0/',
  }),
});

export function referenceFigureFor(source, part, manifest) {
  const figure = Object.hasOwn(figures, source?.figureId) ? figures[source.figureId] : null;
  if (!figure || part?.name !== figure.partName || part.layer !== figure.layer
    || manifest?.species?.scientificName !== figure.species || !sourceAppliesTo(source, part)) return null;
  try {
    const url = new URL(source.url); url.hash = '';
    return url.href === figure.sourceUrl ? figure : null;
  } catch { return null; }
}

let activeDialog;
export function referenceFigureSnapshot() { return activeDialog?.open ? activeDialog.dataset.referenceFigure : null; }
export function closeReferenceFigure() { activeDialog?.close(); }

export function showReferenceFigure(figure, trigger) {
  closeReferenceFigure();
  const dialog = document.createElement('dialog');
  dialog.className = 'anatomy-reference-dialog'; dialog.dataset.referenceFigure = figure.id;
  dialog.setAttribute('aria-labelledby', 'anatomyReferenceTitle');
  dialog.innerHTML = '<button type="button" data-reference-close autofocus>Close reference</button><h2 id="anatomyReferenceTitle">' + e(figure.title) + '</h2><p class="anatomy-reference-limitation">' + e(figure.limitation) + '</p><figure><img src="' + e(figure.imageUrl) + '" alt="Published Figure 5 showing tissue microscopy; panel C is diseased Siganus guttatus kidney tissue" width="600" height="697"><figcaption>' + e(figure.caption) + '</figcaption></figure><p data-reference-image-status role="status">Loading published figure…</p><button type="button" data-reference-image-retry hidden>Retry image</button><p>' + e(figure.credit) + ' <a href="' + e(figure.sourceUrl) + '" target="_blank" rel="noreferrer">Read the study</a> · <a href="' + e(figure.licenseUrl) + '" target="_blank" rel="noreferrer">' + e(figure.licenseName) + '</a></p>';
  const image = dialog.querySelector('img'), status = dialog.querySelector('[data-reference-image-status]'), retry = dialog.querySelector('[data-reference-image-retry]');
  image.onload = () => { status.textContent = 'Published figure loaded. Kidney tissue is in panel C.'; retry.hidden = true; };
  image.onerror = () => { status.textContent = 'Reference image unavailable. Reconnect and retry, or open the study link below.'; retry.hidden = false; };
  retry.onclick = () => { retry.hidden = true; status.textContent = 'Loading published figure…'; image.src = figure.imageUrl; };
  dialog.querySelector('[data-reference-close]').onclick = () => dialog.close();
  const onNavigation = () => dialog.close();
  window.addEventListener('popstate', onNavigation);
  dialog.addEventListener('close', () => {
    window.removeEventListener('popstate', onNavigation);
    if (activeDialog === dialog) activeDialog = null;
    dialog.remove(); if (trigger?.isConnected) trigger.focus();
  }, { once: true });
  (document.getElementById('app') || document.body).append(dialog); activeDialog = dialog;
  dialog.showModal();
  if (image.complete && image.naturalWidth) image.onload();
}
