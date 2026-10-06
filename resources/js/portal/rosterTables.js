import { escapeHtml } from './format.js';

export function rosterTable(key, title, columns, rows) {
  return `<section class="roster-data-panel" data-roster-table="${key}"><h2>${escapeHtml(title)}</h2>
    <div class="roster-table-toolbar"><label>Show <select data-table-size aria-label="Rows per page for ${escapeHtml(title)}"><option>10</option><option>25</option><option>50</option><option>100</option></select> entries</label><label>Search <input type="search" data-table-search aria-label="Search ${escapeHtml(title)}" placeholder="Search this table"></label><button type="button" data-table-clear hidden>Clear search</button><details class="table-columns"><summary>Columns</summary><div>${columns.map((column,index)=>`<label><input type="checkbox" data-table-column="${index}" checked ${index === 0 || column === 'Actions' ? 'disabled' : ''}>${escapeHtml(column)}</label>`).join('')}</div></details><label>Spacing <select data-table-density aria-label="Row spacing for ${escapeHtml(title)}"><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select></label></div>
    <div class="roster-table-scroll" tabindex="0" role="region" aria-label="${escapeHtml(title)} table, scroll horizontally for more columns"><table><caption>${escapeHtml(title)}</caption><thead><tr>${columns.map((column, index) => `<th scope="col" ${column === 'Actions' ? '' : 'aria-sort="none"'}>${column === 'Actions' ? column : `<button type="button" data-table-sort="${index}">${escapeHtml(column)} <span aria-hidden="true">↕</span></button>`}</th>`).join('')}</tr></thead><tbody>${rows}<tr data-table-empty hidden><td colspan="${columns.length}">No records match this view. Clear the search or change the filters.</td></tr></tbody></table></div>
    <div class="roster-table-footer"><span data-table-info role="status" aria-live="polite"></span><div class="roster-table-pages" aria-label="${escapeHtml(title)} pagination"><button type="button" data-table-prev>Previous</button><span data-table-page></span><button type="button" data-table-next>Next</button></div></div></section>`;
}

export function cardsToTable(root, selector, key, title, records = []) {
  const cards = [...root.querySelectorAll(selector)];
  if (!cards.length) return;
  const holder = document.createElement('div');
  holder.innerHTML = rosterTable(key, title, ['Record', 'Summary', 'Status', 'Actions'], cards.map((card, index) => {
    const heading = card.querySelector('h2,h3')?.textContent || 'Record';
    const summary = card.querySelector('.assignment-current-summary,.portal-kicker,p')?.textContent || '';
    const record = records[index];
    const status = record?.status || card.querySelector('.status-badge')?.textContent || '—';
    const version = card.querySelector('.species-card-topline small')?.textContent || '';
    return `<tr data-table-row><td>${escapeHtml(heading)} <small>${escapeHtml(version)}</small></td><td>${escapeHtml(summary)}</td><td><span class="roster-status">${escapeHtml(status)}</span></td><td><button type="button" class="portal-secondary" data-table-expand aria-expanded="false" aria-controls="${key}-${index}">View details</button></td></tr><tr data-table-detail id="${key}-${index}" hidden><td colspan="4"></td></tr>`;
  }).join(''));
  cards[0].parentElement.before(holder);
  cards.forEach((card, index) => holder.querySelector(`#${key}-${index} td`).append(card));
  setupRosterTables(root);
}

// Keep each table's view when an existing account or section action refreshes the roster.
const views = new WeakMap();
export function setupRosterTables(root) {
  if (!views.has(root)) views.set(root, new Map());
  const state = views.get(root);
  root.querySelectorAll('[data-roster-table]').forEach((panel) => {
    if (panel.dataset.tableReady) return;
    panel.dataset.tableReady = 'true';
    const key = panel.dataset.rosterTable;
    if (!state.has(key)) state.set(key, { query: '', size: 10, page: 0, sort: null, descending: false, columns: [], density: 'comfortable' });
    const view = state.get(key);
    const body = panel.querySelector('tbody');
    const rows = [...body.children].filter((row) => row.hasAttribute('data-table-row'));
    const entries = rows.map((row) => ({ row, detail: row.nextElementSibling?.matches('[data-table-detail]') ? row.nextElementSibling : null }));
    const search = panel.querySelector('[data-table-search]');
    const size = panel.querySelector('[data-table-size]');
    search.value = view.query;
    size.value = String(view.size);
    const cellValue = (row, column) => row.cells[column]?.dataset.sortValue ?? row.cells[column]?.textContent.trim() ?? '';
    const update = () => {
      panel.dataset.density = view.density;
      panel.querySelector('[data-table-density]').value = view.density;
      panel.querySelector('[data-table-clear]').hidden = !view.query;
      panel.querySelectorAll('[data-table-column]').forEach(input => { input.checked = !view.columns.includes(Number(input.dataset.tableColumn)); });
      [...panel.querySelectorAll('thead tr,tbody [data-table-row]')].forEach(row => [...row.cells].forEach((cell,index)=>{ cell.hidden = view.columns.includes(index); }));
      panel.querySelectorAll('[data-table-detail] td,[data-table-empty] td').forEach(cell=>{ cell.colSpan = panel.querySelectorAll('thead th:not([hidden])').length; });
      const query = view.query.trim().toLocaleLowerCase();
      const filtered = entries.filter(({ row }) => (row.dataset.search || row.textContent).toLocaleLowerCase().includes(query));
      if (view.sort !== null) filtered.sort((a, b) => String(cellValue(a.row, view.sort)).localeCompare(String(cellValue(b.row, view.sort)), undefined, { numeric: true, sensitivity: 'base' }) * (view.descending ? -1 : 1));
      const pages = Math.max(1, Math.ceil(filtered.length / view.size));
      view.page = Math.min(view.page, pages - 1);
      entries.forEach(({ row, detail }) => { row.hidden = true; if (detail) detail.hidden = true; });
      const start = view.page * view.size;
      filtered.slice(start, start + view.size).forEach(({ row, detail }, index) => {
        row.hidden = false;
        row.classList.toggle('is-striped', index % 2 === 0);
        body.append(row);
        if (detail) {
          body.append(detail);
          detail.hidden = row.querySelector('[data-table-expand]')?.getAttribute('aria-expanded') !== 'true';
        }
      });
      panel.querySelector('[data-table-empty]').hidden = filtered.length > 0;
      panel.querySelector('[data-table-info]').textContent = `Showing ${filtered.length ? start + 1 : 0} to ${Math.min(start + view.size, filtered.length)} of ${filtered.length} entries${query ? ` (filtered from ${entries.length})` : ''}`;
      panel.querySelector('[data-table-page]').textContent = `Page ${view.page + 1} of ${pages}`;
      panel.querySelector('[data-table-prev]').disabled = view.page === 0;
      panel.querySelector('[data-table-next]').disabled = view.page >= pages - 1;
      panel.querySelectorAll('[data-table-sort]').forEach((button) => {
        const selected = Number(button.dataset.tableSort) === view.sort;
        button.closest('th').setAttribute('aria-sort', selected ? (view.descending ? 'descending' : 'ascending') : 'none');
        button.querySelector('span').textContent = selected ? (view.descending ? '↓' : '↑') : '↕';
      });
    };
    search.addEventListener('input', () => { view.query = search.value; view.page = 0; update(); });
    size.addEventListener('change', () => { view.size = Number(size.value); view.page = 0; update(); });
    panel.addEventListener('change', event => {
      const column = event.target.closest('[data-table-column]');
      if (column && !column.disabled) { const index = Number(column.dataset.tableColumn); view.columns = column.checked ? view.columns.filter(value=>value!==index) : [...new Set([...view.columns,index])]; update(); }
      if (event.target.matches('[data-table-density]')) { view.density = event.target.value; update(); }
    });
    panel.addEventListener('click', (event) => {
      if (event.target.closest('[data-table-clear]')) { view.query = ''; search.value = ''; view.page = 0; update(); search.focus(); }
      const sort = event.target.closest('[data-table-sort]');
      if (sort) { const column = Number(sort.dataset.tableSort); view.descending = view.sort === column ? !view.descending : false; view.sort = column; view.page = 0; update(); }
      if (event.target.closest('[data-table-prev]') && view.page > 0) { view.page--; update(); }
      if (event.target.closest('[data-table-next]') && !panel.querySelector('[data-table-next]').disabled) { view.page++; update(); }
      const expand = event.target.closest('[data-table-expand]');
      if (expand) {
        const open = expand.getAttribute('aria-expanded') !== 'true';
        expand.setAttribute('aria-expanded', String(open));
        expand.textContent = open ? 'Close details' : 'View details';
        update();
        expand.focus({ preventScroll: true });
      }
    });
    update();
  });
}
