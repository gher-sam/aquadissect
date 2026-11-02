import { escapeHtml as e } from './portal/format.js';

export const fishProfileFields = { description:'Description', bodyShape:'Body shape', mouthPosition:'Mouth position', tailShape:'Tail shape', finDescription:'Fins and fin structures', anatomicalDistinctions:'Anatomical distinctions', habitat:'Habitat', sizeNotes:'Size and measurement basis', sources:'Sources' };

export function fishDetailsHtml(manifest) {
  const p = manifest.speciesProfile || {};
  const species = manifest.species || {};
  return `<h2>Fish details</h2><p><strong>${e(species.commonName || 'Not supplied')}</strong> · <em>${e(species.scientificName || 'Not supplied')}</em> · v${e(manifest.packageVersion || '')}</p>
    ${manifest.status !== 'published' ? '<p>Staff preview · faculty review and publication are required.</p>' : ''}
    <p>Taxonomic group: ${e(species.taxonomicGroup || 'Not supplied')}</p>
    <h3>Local names</h3>${p.localNames?.length ? '<ul>'+p.localNames.map(n=>`<li><strong>${e(n.name)}</strong> · ${e(n.language)} · ${e(n.locality)}<br>Source: ${e(n.source)}</li>`).join('')+'</ul>' : '<p>Not supplied. Local names alone do not establish species.</p>'}
    <h3>Safety notices</h3><ul>${(manifest.safetyNotices || []).map(n=>'<li>'+e(n)+'</li>').join('') || '<li>Not supplied</li>'}</ul>
    <h3>Package references</h3><ul>${(manifest.references || []).map(r=>'<li>'+e(r.title)+' — '+e(r.publisher || '')+' '+e(r.url || '')+'</li>').join('') || '<li>Not supplied</li>'}</ul>
    <dl>${Object.entries(fishProfileFields).map(([key,label])=>`<dt>${label}</dt><dd>${e(p[key] || (key==='description' ? manifest.contentData?.lesson?.description : '') || 'Not supplied')}</dd>`).join('')}</dl>`;
}

export function showFishDetails(manifest) {
  const dialog=document.createElement('dialog');
  dialog.className='enhancement-dialog';
  dialog.innerHTML='<button type="button" class="portal-secondary" data-close>Close</button>'+fishDetailsHtml(manifest);
  dialog.querySelector('[data-close]').onclick=()=>dialog.close();
  dialog.addEventListener('close',()=>dialog.remove(),{once:true});
  document.body.append(dialog); dialog.showModal();
}
