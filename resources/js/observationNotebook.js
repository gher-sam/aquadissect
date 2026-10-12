import { measurementFields, classificationFields, featureFields, countFields, parseMeasurement, parseCount, notebookCsv } from './observationNotebookData.js';
import '../css/observationNotebook.css';

const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c]);
const notice = 'Unreviewed session worksheet. Not an official submission, calibrated virtual measurement, validated assessment, or proof of biological accuracy. Scientific identity and applicable features require faculty confirmation.';
const stateSelect = (id, measured = false) => `<select id="${id}" name="${id}"><option value="unknown">Unknown</option><option value="not_observed">Not observed</option><option value="not_applicable">Not applicable</option><option value="observed">${measured ? 'Measured' : 'Observed'}</option></select>`;
const textField = (id, label, rows = 2) => `<label class="notebook-field" for="${id}">${escape(label)}<textarea id="${id}" name="${id}" rows="${rows}" maxlength="1000"></textarea></label>`;

export function createObservationNotebook(dialog, trigger) {
  let manifest = null;
  let organFields = [];
  let returnFocus = null;
  const clear = () => {
    if (dialog.open) dialog.close();
    dialog.replaceChildren();
    manifest = null;
    organFields = [];
    trigger.hidden = true;
  };
  function open() {
    if (!manifest) return;
    returnFocus = document.activeElement;
    dialog.showModal();
  }
  function load(next) {
    clear();
    if (next.species?.slug !== 'rabbitfish') return;
    manifest = next;
    organFields = next.contentData.anatomy.structures.filter(part => part.layer === 'internal' || ['gills', 'heart', 'liver', 'gallbladder', 'stomach', 'intestine', 'spleen', 'swimBladder', 'kidney', 'gonad', 'spine'].includes(part.id));
    trigger.hidden = false;
    dialog.innerHTML = `<form class="notebook-form" novalidate>
      <header class="notebook-heading"><div><p class="eyebrow">Rabbitfish preparation</p><h2 id="notebookTitle">Observation notebook</h2></div><button class="text-button" type="button" data-notebook-close aria-label="Close observation notebook">Close</button></header>
      <p class="notebook-notice">Session draft only. Download before leaving the lesson; returning to the dashboard, switching packages, or reloading clears it. Nothing is submitted or stored on this device.</p>
      <p><strong><em>${escape(next.species.scientificName)}</em></strong> · ${escape(next.species.commonName)} · v${escape(next.packageVersion)}<br><small>Package identity; confirmation of the intended danggit specimen is pending. Local names alone do not establish species.</small></p>
      <details open><summary>1. Identify your observation source</summary><div class="notebook-section">
        <label class="notebook-field" for="notebook-basis">Observation source<select id="notebook-basis" name="basis"><option value="virtual_illustration">Virtual illustration (not a real specimen)</option><option value="physical_specimen">Supervised physical specimen</option><option value="reference_material">Reference material</option></select></label>
        ${textField('specimenReference', 'Specimen code or reference (no personal identifiers)')}
        ${textField('referenceAndTools', 'Reference, image magnification, calibrated tools and units, when applicable')}
        <p>Keep illustration notes separate from actual specimen observations. Physical work requires faculty supervision and the approved laboratory protocol.</p>
      </div></details>
      <details><summary>2. Measurements · 16 fields in mm</summary><div class="notebook-section">
        <p>Table 1 field mapping for faculty review. Use calibrated physical measurements or explicitly cited reference values; this image has no calibrated scale. Confirm endpoints and the printed “1st 2nd DF” label with faculty. Blank is never zero.</p>
        ${measurementFields.map(([code, label], i) => `<fieldset class="notebook-row"><legend>${escape(code)} · ${escape(label)}</legend><label for="measure-status-${i}">Record state${stateSelect(`measure-status-${i}`, true)}</label><label for="measure-value-${i}">Value (mm)<input id="measure-value-${i}" name="measure-value-${i}" type="number" min="0.001" step="any" disabled></label></fieldset>`).join('')}
      </div></details>
      <details><summary>3. External features and fin counts</summary><div class="notebook-section">
        <p>This generic laboratory checklist does not imply every feature occurs in rabbitfish. Keep unknown, not observed, absent and not applicable distinct. Confirm terminology and species applicability with faculty.</p>
        ${classificationFields.map((label, i) => `<fieldset class="notebook-row"><legend>${escape(label)}</legend><label for="class-status-${i}">Record state${stateSelect(`class-status-${i}`)}</label><label for="class-value-${i}">Observation<input id="class-value-${i}" name="class-value-${i}" maxlength="200" disabled></label></fieldset>`).join('')}
        <div class="notebook-feature-grid">${featureFields.map((label, i) => `<label for="feature-${i}">${escape(label)}<select id="feature-${i}" name="feature-${i}"><option value="unknown">Unknown</option><option value="not_observed">Not observed</option><option value="not_applicable">Not applicable</option><option value="present">Present</option><option value="absent">Absent</option></select></label>`).join('')}</div>
        <h3>Meristic observations</h3><p>Counts are independent of feature presence. No expected species counts are supplied.</p>
        ${countFields.map((label, i) => `<fieldset class="notebook-row"><legend>${escape(label)}</legend><label for="count-status-${i}">Record state${stateSelect(`count-status-${i}`)}</label><label for="count-value-${i}">Count<input id="count-value-${i}" name="count-value-${i}" type="number" min="0" step="1" disabled></label></fieldset>`).join('')}
      </div></details>
      <details><summary>4. Internal organ observations</summary><div class="notebook-section">
        <p>Examine structures in place first. Describe what you observe separately from interpretation. Record uncertainty; an illustration does not establish specimen condition or an abnormality.</p>
        ${organFields.map((part, i) => `<fieldset class="notebook-organ"><legend>${escape(part.name)}</legend><label for="organ-status-${i}">Record state${stateSelect(`organ-status-${i}`)}</label><div class="notebook-feature-grid">${['Color', 'Position', 'Size / shape', 'Texture', 'Condition', 'Remarks / uncertainty'].map((label, j) => textField(`organ-${i}-${j}`, label)).join('')}</div></fieldset>`).join('')}
      </div></details>
      <details><summary>5. Preparation and reflection</summary><div class="notebook-section">
        ${textField('orientation', 'Describe symmetry, anatomical planes and directional landmarks using your faculty-approved reference.')}
        ${textField('sequence', 'Describe external examination, supervised opening, organs in place and cleanup. What requires clarification?')}
        ${textField('interpretation', 'Separate direct observations from interpretations and unresolved questions.')}
        ${textField('physicalFollowUp', 'Which measurements, scale microscopy, actual specimen photographs and comparisons remain for the physical laboratory?')}
        <p>The worksheet supports preparation. Use the instructor’s actual guide questions and required worksheet or LMS submission. Screenshots of virtual anatomy are not real specimen photographs.</p>
      </div></details>
      <footer class="notebook-footer"><p id="notebookFeedback" role="status" aria-live="polite">${escape(notice)}</p><div><button class="primary-button" type="button" data-notebook-export="json">Download JSON</button><button class="text-button" type="button" data-notebook-export="csv">Download CSV</button><button class="text-button" type="button" data-notebook-reset>Clear draft</button></div><div class="notebook-reset" hidden><p>Clear all notebook entries? Download a copy first if needed.</p><button class="text-button" type="button" data-notebook-confirm-reset>Clear entries</button><button class="text-button" type="button" data-notebook-cancel-reset>Keep entries</button></div></footer>
    </form>`;
  }
  function collect() {
    const form = dialog.querySelector('form');
    const focusField = name => { const input = form.elements.namedItem(name); const section = input.closest('details'); if (section) section.open = true; input.focus(); };
    const data = new FormData(form);
    const get = key => String(data.get(key) ?? '').trim();
    if (get('basis') === 'virtual_illustration' && measurementFields.some((_, i) => get(`measure-status-${i}`) === 'observed')) throw new Error('Virtual images have no calibrated scale. Use a physical specimen or cited reference source for measured values.');
    const measurements = measurementFields.map(([code, label], i) => {
      const status = get(`measure-status-${i}`);
      try { return { code, label, status, value: parseMeasurement(get(`measure-value-${i}`), status), unit: 'mm' }; }
      catch (error) { focusField(`measure-value-${i}`); throw new Error(`${code}: ${error.message}`); }
    });
    const counts = countFields.map((field, i) => {
      const status = get(`count-status-${i}`);
      try { return { field, status, value: parseCount(get(`count-value-${i}`), status) }; }
      catch (error) { focusField(`count-value-${i}`); throw new Error(`${field}: ${error.message}`); }
    });
    const classifications = classificationFields.map((field, i) => {
      const status = get(`class-status-${i}`); const value = status === 'observed' ? get(`class-value-${i}`) : null;
      if (status === 'observed' && !value) { focusField(`class-value-${i}`); throw new Error(`${field}: enter an observation or select a missing-data state.`); }
      return { field, status, value };
    });
    return { schemaVersion: 1, exportedAt: new Date().toISOString(), notice,
      package: { slug: manifest.species.slug, scientificName: manifest.species.scientificName, version: manifest.packageVersion },
      basis: get('basis'), specimenReference: get('specimenReference'), referenceAndTools: get('referenceAndTools'),
      measurements, classifications, counts, features: featureFields.map((field, i) => ({ field, status: get(`feature-${i}`) })),
      organs: organFields.map((part, i) => ({ structure: part.id, status: get(`organ-status-${i}`),
        ...Object.fromEntries(['color', 'position', 'sizeShape', 'texture', 'condition', 'remarks'].map((key, j) => [key, get(`organ-${i}-${j}`)])) })),
      reflections: Object.fromEntries(['orientation', 'sequence', 'interpretation', 'physicalFollowUp'].map(key => [key, get(key)])),
    };
  }
  trigger.addEventListener('click', open);
  dialog.addEventListener('close', () => { if (returnFocus?.isConnected) returnFocus.focus(); });
  dialog.addEventListener('submit', event => event.preventDefault());
  dialog.addEventListener('change', event => {
    const match = event.target.name?.match(/^(measure|count|class)-status-(\d+)$/);
    if (!match) return;
    const input = dialog.querySelector(`#${match[1]}-value-${match[2]}`);
    input.disabled = event.target.value !== 'observed';
    if (input.disabled) input.value = '';
  });
  dialog.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.hasAttribute('data-notebook-close')) dialog.close();
    const resetPanel = dialog.querySelector('.notebook-reset');
    if (button.hasAttribute('data-notebook-reset')) { resetPanel.hidden = false; resetPanel.querySelector('button').focus(); }
    if (button.hasAttribute('data-notebook-cancel-reset')) { resetPanel.hidden = true; dialog.querySelector('[data-notebook-reset]').focus(); }
    if (button.hasAttribute('data-notebook-confirm-reset')) { dialog.querySelector('form').reset(); dialog.querySelectorAll('input[id*=-value-]').forEach(input => { input.disabled = true; input.value = ''; }); resetPanel.hidden = true; dialog.querySelector('#notebookFeedback').textContent = notice; dialog.querySelector('[data-notebook-reset]').focus(); }
    if (!button.dataset.notebookExport) return;
    const feedback = dialog.querySelector('#notebookFeedback');
    try {
      const draft = collect();
      const csv = button.dataset.notebookExport === 'csv';
      const url = URL.createObjectURL(new Blob([csv ? notebookCsv(draft) : JSON.stringify(draft, null, 2)], { type: csv ? 'text/csv;charset=utf-8' : 'application/json' }));
      const link = document.createElement('a'); link.href = url; link.download = `rabbitfish-observations.${csv ? 'csv' : 'json'}`; link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
      feedback.textContent = 'Download requested. This draft is not submitted to your instructor.';
    } catch (error) { feedback.textContent = error.message; }
  });
  return { load, clear, open };
}
