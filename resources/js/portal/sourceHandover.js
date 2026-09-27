import { request, appUrl } from './api.js';
import { escapeHtml as e } from './format.js';

export async function sourceHandover(root) {
  const release = await request('/api/admin/source-handover');
  const endpoint = path => appUrl('/api/admin/source-handover/' + path);
  root.innerHTML = `<section class="workspace-panel"><div class="workspace-toolbar"><div><h2>Release ${e(release.revision.slice(0,12))}</h2><p>${release.files.length} files · ${(release.bytes/1048576).toFixed(1)} MB · Prepared ${e(new Date(release.preparedAt).toLocaleString())}</p></div><a class="btn btn-primary" href="${endpoint('download')}">Download source ZIP</a></div><details><summary>Package contents and integrity</summary><p>Application code, dependency lockfiles, migrations, local setup instructions, and project learning assets. No live data, secrets, uploads, manuscripts, or installed dependencies.</p><p class="source-hash">SHA-256: ${e(release.sha256)}</p><p>Downloads are logged with your account ID and release revision. Package assets retain their internal development usage terms.</p></details></section><section class="workspace-panel source-browser"><div><label for="sourceFilter">Find a source file</label><input id="sourceFilter" type="search" placeholder="Filter by file path"><div id="sourceTree" class="source-tree" aria-label="Source folders and files"></div></div><div class="source-preview"><div class="workspace-toolbar"><h2 id="sourcePath">Select a file</h2><a id="sourceFileDownload" class="btn btn-outline-primary" hidden>Download file</a></div><p id="sourcePreviewStatus" role="status">Expand a folder to browse. Text previews are read only.</p><pre tabindex="0" aria-label="Read-only source preview"><code id="sourceCode"></code></pre></div></section>`;
  const tree = root.querySelector('#sourceTree');
  const filter = root.querySelector('#sourceFilter');
  function render() {
    const query=filter.value.trim().toLowerCase(); const folders={};
    for (const file of release.files.filter(f=>f.path.toLowerCase().includes(query))) {
      const parts=file.path.split('/'); let node=folders;
      parts.forEach((part,index)=> { if(index===parts.length-1) node[part]=file; else node=node[part] ||= {}; });
    }
    function html(node) { return '<ul>'+Object.entries(node).map(([name,value])=>value.path ? `<li><button type="button" data-source-file="${e(value.path)}">${e(name)}</button></li>` : `<li><details ${query?'open':''}><summary>${e(name)}</summary>${html(value)}</details></li>`).join('')+'</ul>'; }
    tree.innerHTML=Object.keys(folders).length ? html(folders) : '<p>No matching files.</p>';
  }
  filter.addEventListener('input',render); render(); let selection=0;
  tree.addEventListener('click',async event=> {
    const button=event.target.closest('[data-source-file]'); if(!button) return;
    const path=button.dataset.sourceFile; const ticket=++selection;
    root.querySelector('#sourcePath').textContent=path;
    const download=root.querySelector('#sourceFileDownload'); download.hidden=false; download.href=endpoint('file')+'?path='+encodeURIComponent(path);
    const status=root.querySelector('#sourcePreviewStatus'); const code=root.querySelector('#sourceCode');
    code.textContent=''; status.textContent='Loading preview…';
    try { const result=await request('/api/admin/source-handover/preview?path='+encodeURIComponent(path)); if(ticket!==selection)return; code.textContent=result.content; status.textContent='Read-only preview'; }
    catch(error) { if(ticket===selection) status.textContent=error.message; }
  });
}
