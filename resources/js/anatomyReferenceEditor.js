import { escapeHtml as e } from './portal/format.js';

export function mountAnatomyReferenceEditor(host, structures, onChange) {
  let map, current, disabled = true;
  host.innerHTML = '<legend>Anatomy references</legend><p>Edit a citation without changing the parts it supports. To replace a publication, add a new reference and remove the old one. References do not record faculty approval.</p><div data-reference-list></div><button type="button" data-reference-add>Add reference</button>';
  const list = host.querySelector('[data-reference-list]');
  function render() {
    list.replaceChildren();
    const parts = structures.filter(part => part.layer === current.layer);
    for (const source of map.sources) {
      const row = document.createElement('details'), wholeImage = !Object.hasOwn(source, 'appliesTo');
      row.className = 'region-reference';
      row.open = !source.title;
      row.innerHTML = `<summary><strong>${e(source.title || 'New reference')}</strong><small data-reference-summary></small></summary><label>Reference title<input data-reference-title value="${e(source.title || '')}" required></label><label>HTTPS source link<input data-reference-url type="url" value="${e(source.url || '')}" required></label>${source.publisher ? `<p>${e(source.publisher)}</p>` : ''}<label>What this source shows or cannot establish<textarea data-reference-scope rows="3">${e(source.scope || '')}</textarea></label><label class="region-reference-choice"><input type="checkbox" data-reference-whole ${wholeImage ? 'checked' : ''}>Applies to every part in this image</label><fieldset data-reference-parts ${wholeImage ? 'disabled' : ''}><legend>Supported parts</legend>${parts.map(part => `<label class="region-reference-choice"><input type="checkbox" data-reference-part value="${e(part.id)}" ${source.appliesTo?.some(target => target.id === part.id && target.label === part.name) ? 'checked' : ''}>${e(part.name)}</label>`).join('')}</fieldset><button type="button" data-reference-remove>Remove reference</button>`;
      const whole = row.querySelector('[data-reference-whole]'), choices = [...row.querySelectorAll('[data-reference-part]')];
      const summary = () => {
        row.querySelector('[data-reference-summary]').textContent = Object.hasOwn(source, 'appliesTo')
          ? `Supports: ${source.appliesTo.map(target => target.label).join(', ') || 'Select at least one part'}` : 'Supports every part in this image';
      };
      for (const key of ['title', 'url', 'scope']) row.querySelector(`[data-reference-${key}]`).addEventListener('input', event => {
        source[key] = event.target.value;
        if (key === 'title') row.querySelector('summary strong').textContent = source.title || 'New reference';
        onChange();
      });
      const saveParts = () => {
        source.appliesTo = choices.filter(choice => choice.checked).map(choice => {
          const part = parts.find(part => part.id === choice.value);
          return { id: part.id, label: part.name };
        });
        summary(); onChange();
      };
      whole.addEventListener('change', () => {
        row.querySelector('[data-reference-parts]').disabled = whole.checked;
        if (whole.checked) { delete source.appliesTo; summary(); onChange(); }
        else {
          if (!choices.some(choice => choice.checked)) choices.find(choice => choice.value === current.id).checked = true;
          saveParts();
        }
      });
      for (const choice of choices) choice.addEventListener('change', saveParts);
      row.querySelector('[data-reference-remove]').onclick = () => { map.sources.splice(map.sources.indexOf(source), 1); onChange(); render(); };
      summary(); list.append(row);
    }
    host.disabled = disabled;
  }
  host.querySelector('[data-reference-add]').onclick = () => {
    map.sources.push({ title: '', url: '', appliesTo: [{ id: current.id, label: current.name }] });
    onChange(); render();
    list.lastElementChild.querySelector('[data-reference-title]').focus();
  };
  return {
    load(nextMap, part) { map = nextMap; current = part; render(); },
    setDisabled(value) { disabled = value; host.disabled = disabled; },
  };
}
