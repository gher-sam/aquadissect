import { ChevronRight, File, Folder, FolderOpen, Search, X, createIcons } from 'lucide';
import { request, appUrl } from './api.js';
import { escapeHtml as e } from './format.js';
import vsCodeLogo from '../../css/product-logos/vs-code.png';
import githubLogo from '../../css/product-logos/github.svg';
import hostingerLogo from '../../css/product-logos/hostinger.svg';
import aaPanelLogo from '../../css/product-logos/aapanel.png';

const iconSet = { ChevronRight, File, Folder, FolderOpen, Search, X };
const refreshIcons = root => createIcons({
  icons: iconSet,
  root,
  attrs: { 'stroke-width': 1.8, 'aria-hidden': 'true' },
});
const naturalSort = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' });

function formatSize(value = 0) {
  const bytes = Number(value) || 0;
  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(bytes < 10240 ? 1 : 0)} KB`;
  return `${(bytes / 1048576).toFixed(1)} MB`;
}

function fileType(path) {
  const lower = path.toLowerCase();
  if (lower.endsWith('.blade.php')) return 'Blade template';
  const extension = lower.split('.').pop();
  const labels = {
    css: 'CSS stylesheet', glb: '3D model', html: 'HTML document', jpg: 'JPEG image',
    js: 'JavaScript file', json: 'JSON file', lock: 'Dependency lockfile', md: 'Markdown document',
    mjs: 'JavaScript module', mp4: 'MP4 video', php: 'PHP file', png: 'PNG image',
    scss: 'Sass stylesheet', svg: 'SVG image', txt: 'Text file', webp: 'WebP image',
  };
  return labels[extension] || `${extension ? extension.toUpperCase() : 'Source'} file`;
}

function makeTree(files) {
  const root = { type: 'folder', name: 'Source files', path: '', children: [], childMap: new Map(), parentPath: '' };
  const folders = [];
  const nodesByPath = new Map();

  for (const file of files) {
    const parts = file.path.split('/');
    let parent = root;
    let folderPath = '';
    parts.forEach((part, index) => {
      const path = folderPath ? `${folderPath}/${part}` : part;
      if (index === parts.length - 1) {
        const node = { type: 'file', name: part, path: file.path, bytes: file.bytes, parentPath: parent.path };
        parent.children.push(node);
        nodesByPath.set(node.path, node);
        return;
      }
      if (!parent.childMap.has(part)) {
        const folder = { type: 'folder', name: part, path, children: [], childMap: new Map(), parentPath: parent.path };
        parent.childMap.set(part, folder);
        parent.children.push(folder);
        folders.push(folder);
        nodesByPath.set(folder.path, folder);
      }
      parent = parent.childMap.get(part);
      folderPath = path;
    });
  }

  const sortChildren = node => {
    node.children.sort((left, right) => {
      if (left.type !== right.type) return left.type === 'folder' ? -1 : 1;
      return naturalSort.compare(left.name, right.name);
    });
    node.children.filter(child => child.type === 'folder').forEach(sortChildren);
  };
  const countFiles = node => {
    node.fileCount = node.children.reduce((total, child) => total + (child.type === 'file' ? 1 : countFiles(child)), 0);
    return node.fileCount;
  };
  sortChildren(root);
  countFiles(root);

  return { root, folders, nodesByPath };
}

export async function sourceHandover(root) {
  root._sourceHandoverCleanup?.();
  const release = await request('/api/admin/source-handover');
  const endpoint = path => appUrl(`/api/admin/source-handover/${path}`);
  const files = [...release.files].sort((left, right) => naturalSort.compare(left.path, right.path));
  const fileByPath = new Map(files.map(file => [file.path, file]));
  const hierarchy = makeTree(files);
  const itemIds = new Map([...hierarchy.nodesByPath.keys()].map((path, index) => [path, `sourceTreeItem${index + 1}`]));
  const expanded = new Set(hierarchy.root.children.filter(node => node.type === 'folder').map(node => node.path));
  let filterExpanded = new Set();
  let query = '';
  let activePath = hierarchy.root.children[0]?.path || '';
  let selectedPath = '';
  let previewController;
  let compactPreviewOpen = false;
  let typeahead = '';
  let typeaheadTimer;
  const compactQuery = matchMedia('(max-width: 1100px)');
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');

  root.innerHTML = `
    <figure class="workspace-panel source-delivery" aria-labelledby="sourceDeliveryTitle" aria-describedby="sourceDeliveryCaption">
      <header class="source-delivery-heading"><span class="portal-kicker">From code to website</span><h2 id="sourceDeliveryTitle">How AquaDissect reaches the web</h2><p>Follow the tools used to prepare, store, host, and publish the application.</p></header>
      <ol class="source-delivery-flow" role="list">
        <li><span class="source-delivery-number">01 · Create</span><div class="source-delivery-logo"><img src="${vsCodeLogo}" alt="Visual Studio Code logo" width="56" height="56"></div><h3>VS Code</h3><p>Write and check the application code in Visual Studio Code.</p><span class="source-delivery-action">Prepare the update</span></li>
        <li><span class="source-delivery-number">02 · Store</span><div class="source-delivery-logo"><img src="${githubLogo}" alt="GitHub logo" width="56" height="56"></div><h3>GitHub</h3><p>Keep version history and send the checked code update to the repository.</p><span class="source-delivery-action">Trigger deployment</span></li>
        <li><span class="source-delivery-number">03 · Host</span><div class="source-delivery-logo"><img src="${hostingerLogo}" alt="Hostinger logo" width="56" height="56"></div><h3>Hostinger</h3><p>Provide the virtual server that stores and runs the live application.</p><span class="source-delivery-action">Run the website server</span></li>
        <li><span class="source-delivery-number">04 · Manage</span><div class="source-delivery-logo"><img src="${aaPanelLogo}" alt="aaPanel logo" width="48" height="48"></div><h3>aaPanel</h3><p>Manage the website, database, and HTTPS settings on that server.</p><span class="source-delivery-action">Support the live site</span></li>
      </ol>
      <figcaption id="sourceDeliveryCaption"><strong>Result: AquaDissect online</strong><span>A GitHub update triggers the deployment script on the Hostinger server. The script prepares the new release; aaPanel manages the hosting environment on the same server.</span></figcaption>
    </figure>
    <section class="workspace-panel">
      <div class="workspace-toolbar">
        <div><h2>Release ${e(release.revision.slice(0, 12))}</h2><p>${files.length} files · ${e(formatSize(release.bytes))} · Prepared ${e(new Date(release.preparedAt).toLocaleString())}</p></div>
        <a class="btn btn-primary" href="${endpoint('download')}">Download source ZIP</a>
      </div>
    </section>
    <section class="workspace-panel source-browser">
      <div class="source-tree-pane" id="sourceFileBrowser">
        <div class="source-tree-header">
          <div><h2>Source files</h2><p class="source-tree-count" id="sourceMatchSummary">${files.length} files</p></div>
          <div class="source-tree-toolbar">
            <label class="sr-only" for="sourceFilter">Find a source file</label>
            <div class="source-filter-wrap"><i class="source-filter-icon" data-lucide="search"></i><input id="sourceFilter" type="search" placeholder="Search files" autocomplete="off" aria-describedby="sourceMatchSummary sourceSearchHelp"><button class="source-filter-clear" type="button" data-clear-source-search aria-label="Clear source file search" hidden><i data-lucide="x"></i></button></div>
            <div class="source-tree-actions"><button type="button" data-tree-expand-all aria-label="Expand all folders" title="Expand all folders"><span aria-hidden="true">+</span></button><button type="button" data-tree-collapse-all aria-label="Collapse all folders" title="Collapse all folders"><span aria-hidden="true">−</span></button></div>
          </div>
          <p class="sr-only" id="sourceSearchHelp">Search results open their matching folders automatically.</p>
          <p class="source-tree-status" id="sourceTreeStatus" role="status" aria-live="polite" aria-atomic="true">Use the arrow keys to move through the file tree.</p>
        </div>
        <div id="sourceTree" class="source-tree" role="tree" aria-label="Source folders and files" aria-multiselectable="false"></div>
        <div class="source-tree-empty" id="sourceTreeEmpty" hidden><strong>No matching source files</strong><p>Try a shorter file name or clear the search to browse all ${files.length} files.</p><button type="button" data-empty-clear-search>Clear search</button></div>
      </div>
      <div class="source-preview" id="sourcePreview" aria-busy="false">
        <button class="source-back-to-files" type="button" data-back-to-files hidden><span aria-hidden="true">←</span> Back to files</button>
        <div class="source-preview-header"><div><h2 id="sourcePreviewHeading">File preview</h2><p class="source-preview-path" id="sourcePath">Select a file from the tree.</p><p class="source-preview-meta" id="sourceFileMeta"></p></div><a id="sourceFileDownload" class="btn btn-outline-primary" hidden>Download file</a></div>
        <p class="source-preview-status" id="sourcePreviewStatus" role="status" aria-live="polite" aria-atomic="true">Expand a folder to browse. Text previews are read only.</p>
        <div class="source-preview-empty"><div><i data-lucide="file" aria-hidden="true"></i><strong>Select a file to preview</strong><p>Choose a text file from the tree. The source remains read only.</p></div></div>
        <pre class="source-preview-code" tabindex="0" aria-label="Read-only source preview" hidden><code id="sourceCode"></code></pre>
      </div>
    </section>`;

  const tree = root.querySelector('#sourceTree');
  const treeEmpty = root.querySelector('#sourceTreeEmpty');
  const filter = root.querySelector('#sourceFilter');
  const clearSearch = root.querySelector('[data-clear-source-search]');
  const matchSummary = root.querySelector('#sourceMatchSummary');
  const treeStatus = root.querySelector('#sourceTreeStatus');
  const preview = root.querySelector('#sourcePreview');
  const previewPath = root.querySelector('#sourcePath');
  const previewMeta = root.querySelector('#sourceFileMeta');
  const previewStatus = root.querySelector('#sourcePreviewStatus');
  const previewCode = root.querySelector('#sourceCode');
  const previewPane = preview.querySelector('pre');
  const previewEmpty = preview.querySelector('.source-preview-empty');
  const download = root.querySelector('#sourceFileDownload');
  const backButton = root.querySelector('[data-back-to-files]');
  const sourceBrowser = preview.closest('.source-browser');
  refreshIcons(root);

  const expansionState = () => query ? filterExpanded : expanded;
  const matchesQuery = file => !query || file.path.toLocaleLowerCase().includes(query);
  const matchingFiles = () => files.filter(matchesQuery);
  const nodeHasMatch = node => node.type === 'file' ? matchesQuery(node) : node.children.some(nodeHasMatch);
  const visibleFileCount = node => query
    ? node.children.reduce((total, child) => total + (child.type === 'file' ? Number(matchesQuery(child)) : visibleFileCount(child)), 0)
    : node.fileCount;

  const announceTree = message => {
    treeStatus.textContent = '';
    requestAnimationFrame(() => { treeStatus.textContent = message; });
  };

  const visibleItems = () => [...tree.querySelectorAll('[role="treeitem"]')]
    .filter(item => !item.closest('[role="group"][hidden]'));

  const setRovingFocus = item => {
    if (!item) return;
    tree.querySelectorAll('[role="treeitem"]').forEach(candidate => { candidate.tabIndex = candidate === item ? 0 : -1; });
    activePath = item.dataset.treePath;
    item.focus();
  };

  const renderNodes = (nodes, level) => {
    const visibleNodes = nodes.filter(nodeHasMatch);
    return visibleNodes.map((node, index) => {
      const id = itemIds.get(node.path);
      const selected = selectedPath === node.path;
      const common = `id="${id}" role="treeitem" data-tree-path="${e(node.path)}" data-tree-kind="${node.type}" data-parent-path="${e(node.parentPath)}" tabindex="${activePath === node.path ? '0' : '-1'}" aria-selected="${selected}" aria-level="${level}" aria-posinset="${index + 1}" aria-setsize="${visibleNodes.length}"`;
      if (node.type === 'file') {
        return `<li role="none"><button type="button" class="source-tree-row is-file${selected ? ' is-selected' : ''}" ${common}><span class="source-tree-chevron" aria-hidden="true"></span><span class="source-tree-icon"><i data-lucide="file"></i></span><span class="source-tree-label">${e(node.name)}</span><span class="source-tree-meta">${e(fileType(node.path))} · ${e(formatSize(node.bytes))}</span></button></li>`;
      }
      const open = expansionState().has(node.path);
      const fileCount = visibleFileCount(node);
      const groupId = `${id}Group`;
      return `<li role="none"><button type="button" class="source-tree-row is-folder${selected ? ' is-selected' : ''}" ${common} aria-expanded="${open}" aria-owns="${groupId}"><span class="source-tree-chevron"><i data-lucide="chevron-right"></i></span><span class="source-tree-icon"><i data-lucide="${open ? 'folder-open' : 'folder'}"></i></span><span class="source-tree-label">${e(node.name)}</span><span class="source-tree-meta">${fileCount} ${fileCount === 1 ? 'file' : 'files'}</span></button><ul class="source-tree-group" id="${groupId}" role="group" aria-label="${e(node.name)} contents" ${open ? '' : 'hidden'}>${renderNodes(node.children, level + 1)}</ul></li>`;
    }).join('');
  };

  const renderTree = ({ focusPath = '', focus = false } = {}) => {
    const matches = matchingFiles();
    matchSummary.textContent = query ? `${matches.length} of ${files.length} files match` : `${files.length} files`;
    clearSearch.hidden = !query;
    if (!matches.length) {
      tree.innerHTML = '';
      tree.hidden = true;
      treeEmpty.hidden = false;
      refreshIcons(tree);
      return;
    }
    tree.hidden = false;
    treeEmpty.hidden = true;
    tree.innerHTML = `<ul class="source-tree-root" role="none">${renderNodes(hierarchy.root.children, 1)}</ul>`;
    let roving = tree.querySelector(`[data-tree-path="${CSS.escape(focusPath || activePath)}"]`);
    if (!roving || roving.closest('[role="group"][hidden]')) roving = visibleItems()[0];
    if (roving) {
      tree.querySelectorAll('[role="treeitem"]').forEach(item => { item.tabIndex = item === roving ? 0 : -1; });
      activePath = roving.dataset.treePath;
      if (focus) roving.focus();
    }
    refreshIcons(tree);
  };

  const toggleFolder = (item, force) => {
    const path = item.dataset.treePath;
    const state = expansionState();
    const shouldOpen = force ?? !state.has(path);
    if (shouldOpen) state.add(path); else state.delete(path);
    renderTree({ focusPath: path, focus: true });
    announceTree(`${hierarchy.nodesByPath.get(path)?.name || 'Folder'} ${shouldOpen ? 'expanded' : 'collapsed'}.`);
  };

  const selectFile = async item => {
    const path = item.dataset.treePath;
    const file = fileByPath.get(path);
    if (!file) return;
    selectedPath = path;
    tree.querySelectorAll('[role="treeitem"]').forEach(candidate => {
      const selected = candidate.dataset.treePath === selectedPath;
      candidate.setAttribute('aria-selected', String(selected));
      candidate.classList.toggle('is-selected', selected);
      candidate.setAttribute('aria-busy', String(selected));
    });
    previewController?.abort();
    const controller = new AbortController();
    previewController = controller;
    preview.setAttribute('aria-busy', 'true');
    previewPath.textContent = path;
    previewMeta.textContent = `${fileType(path)} · ${formatSize(file.bytes)}`;
    download.hidden = false;
    download.href = `${endpoint('file')}?path=${encodeURIComponent(path)}`;
    download.setAttribute('download', path.split('/').pop());
    previewEmpty.hidden = true;
    previewPane.hidden = false;
    previewCode.textContent = '';
    previewPane.setAttribute('aria-label', `Read-only preview of ${path}`);
    previewPane.scrollTop = 0;
    previewPane.scrollLeft = 0;
    previewStatus.setAttribute('role', 'status');
    previewStatus.setAttribute('aria-live', 'polite');
    previewStatus.classList.remove('is-error');
    previewStatus.textContent = 'Loading read-only preview…';
    compactPreviewOpen = true;
    if (compactQuery.matches) {
      sourceBrowser?.classList.add('is-previewing');
      preview.scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' });
      requestAnimationFrame(() => backButton.focus({ preventScroll: true }));
    }

    try {
      const response = await fetch(appUrl(`/api/admin/source-handover/preview?path=${encodeURIComponent(path)}`), {
        credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: controller.signal,
      });
      const result = await response.json().catch(() => ({}));
      if ([401, 419].includes(response.status) && window.AQUADISSECT_BOOTSTRAP?.user) {
        window.location.assign(appUrl('/login'));
        return;
      }
      if (!response.ok) throw new Error(result.message || 'Preview unavailable');
      if (selectedPath !== path || controller.signal.aborted) return;
      previewCode.textContent = result.content;
      previewPane.scrollTop = 0;
      previewPane.scrollLeft = 0;
      previewStatus.textContent = 'Read-only preview loaded.';
    } catch (error) {
      if (error.name === 'AbortError' || selectedPath !== path) return;
      previewStatus.setAttribute('role', 'alert');
      previewStatus.setAttribute('aria-live', 'assertive');
      previewStatus.classList.add('is-error');
      previewStatus.textContent = 'Preview unavailable. This file may not support a text preview. You can still download it.';
    } finally {
      if (previewController === controller) {
        preview.setAttribute('aria-busy', 'false');
        tree.querySelector(`[data-tree-path="${CSS.escape(path)}"]`)?.setAttribute('aria-busy', 'false');
      }
    }
  };

  const activateItem = item => {
    if (item.dataset.treeKind === 'folder') toggleFolder(item);
    else void selectFile(item);
  };

  tree.addEventListener('click', event => {
    const item = event.target.closest('[role="treeitem"]');
    if (item) activateItem(item);
  });
  treeEmpty.querySelector('[data-empty-clear-search]').addEventListener('click', () => clearSearch.click());
  tree.addEventListener('focusin', event => {
    const item = event.target.closest('[role="treeitem"]');
    if (item) {
      activePath = item.dataset.treePath;
      tree.querySelectorAll('[role="treeitem"]').forEach(candidate => { candidate.tabIndex = candidate === item ? 0 : -1; });
    }
  });
  tree.addEventListener('keydown', event => {
    const item = event.target.closest('[role="treeitem"]');
    if (!item) return;
    const items = visibleItems();
    const index = items.indexOf(item);
    if (event.key === 'ArrowDown') setRovingFocus(items[Math.min(index + 1, items.length - 1)]);
    else if (event.key === 'ArrowUp') setRovingFocus(items[Math.max(index - 1, 0)]);
    else if (event.key === 'Home') setRovingFocus(items[0]);
    else if (event.key === 'End') setRovingFocus(items.at(-1));
    else if (event.key === 'ArrowRight' && item.dataset.treeKind === 'folder') {
      if (item.getAttribute('aria-expanded') === 'false') toggleFolder(item, true);
      else setRovingFocus(item.nextElementSibling?.querySelector('[role="treeitem"]'));
    } else if (event.key === 'ArrowLeft') {
      if (item.dataset.treeKind === 'folder' && item.getAttribute('aria-expanded') === 'true') toggleFolder(item, false);
      else setRovingFocus(tree.querySelector(`[data-tree-path="${CSS.escape(item.dataset.parentPath)}"]`));
    } else if (event.key === 'Enter' || event.key === ' ') activateItem(item);
    else if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
      typeahead += event.key.toLocaleLowerCase();
      clearTimeout(typeaheadTimer);
      typeaheadTimer = setTimeout(() => { typeahead = ''; }, 700);
      const ordered = [...items.slice(index + 1), ...items.slice(0, index + 1)];
      const match = ordered.find(candidate => hierarchy.nodesByPath.get(candidate.dataset.treePath)?.name.toLocaleLowerCase().startsWith(typeahead));
      if (match) setRovingFocus(match);
    } else return;
    event.preventDefault();
  });

  filter.addEventListener('input', () => {
    query = filter.value.trim().toLocaleLowerCase();
    filterExpanded = new Set(query ? hierarchy.folders.filter(nodeHasMatch).map(folder => folder.path) : []);
    renderTree();
    const count = matchingFiles().length;
    announceTree(query ? `${count} ${count === 1 ? 'file matches' : 'files match'} your search.` : `Showing all ${files.length} files.`);
  });
  clearSearch.addEventListener('click', () => {
    filter.value = '';
    query = '';
    filterExpanded.clear();
    renderTree();
    filter.focus();
    announceTree(`Search cleared. Showing all ${files.length} files.`);
  });
  root.querySelector('[data-tree-expand-all]').addEventListener('click', () => {
    const state = expansionState();
    hierarchy.folders.filter(nodeHasMatch).forEach(folder => state.add(folder.path));
    renderTree();
    announceTree('All visible folders expanded.');
  });
  root.querySelector('[data-tree-collapse-all]').addEventListener('click', () => {
    expansionState().clear();
    renderTree();
    announceTree('All visible folders collapsed.');
  });
  backButton.addEventListener('click', () => {
    compactPreviewOpen = false;
    sourceBrowser?.classList.remove('is-previewing');
    root.querySelector('#sourceFileBrowser').scrollIntoView({ behavior: reducedMotion.matches ? 'auto' : 'smooth', block: 'start' });
    (tree.querySelector(`[data-tree-path="${CSS.escape(selectedPath || activePath)}"]`) || filter).focus({ preventScroll: true });
  });
  const updateCompactControl = () => {
    const wasBackFocused = document.activeElement === backButton;
    backButton.hidden = !compactQuery.matches;
    sourceBrowser?.classList.toggle('is-previewing', compactQuery.matches && compactPreviewOpen);
    if (!compactQuery.matches && wasBackFocused) {
      (selectedPath && !previewPane.hidden ? previewPane : tree.querySelector('[tabindex="0"]') || filter).focus({ preventScroll: true });
    }
  };
  compactQuery.addEventListener?.('change', updateCompactControl);
  updateCompactControl();
  renderTree();

  root._sourceHandoverCleanup = () => {
    previewController?.abort();
    clearTimeout(typeaheadTimer);
    compactQuery.removeEventListener?.('change', updateCompactControl);
  };
}
