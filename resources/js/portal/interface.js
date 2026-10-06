// UI preferences contain no account or learning data.
export function setupNavigationGroups(portal) {
  if (!portal.classList.contains('is-authenticated')) return;
  const navigation = portal.querySelector('.portal-nav');
  if (navigation.dataset.grouped) return;
  navigation.dataset.grouped = 'true';
  const headings = [...navigation.querySelectorAll('.portal-nav-section')];
  headings.forEach((heading,index) => {
    const links=[];
    let next=heading.nextElementSibling;
    while(next && !next.classList.contains('portal-nav-section')) { links.push(next); next=next.nextElementSibling; }
    if (heading.hidden || !links.some(link=>!link.hidden)) return;
    const group=document.createElement('section'); group.className='navigation-group';
    const button=document.createElement('button'); button.type='button'; button.className='navigation-group-toggle';
    const body=document.createElement('div'); body.id=`navigation-group-${index}`; body.className='navigation-group-links';
    button.setAttribute('aria-controls',body.id);
    button.innerHTML=`<span>${heading.textContent}</span><span class="navigation-chevron" aria-hidden="true">⌄</span>`;
    heading.before(group); group.append(button,body); links.forEach(link=>body.append(link)); heading.remove();
    const key=`aquadissect-navigation-${index}`;
    let closed=false;
    try { closed=localStorage.getItem(key)==='closed'; } catch { /* Preferences are optional. */ }
    if (body.querySelector('.is-active') || body.querySelector(`[data-portal-page="${window.AQUADISSECT_BOOTSTRAP?.page}"]`)) closed=false;
    const show=expanded=>{ body.hidden=!expanded; button.setAttribute('aria-expanded',String(expanded)); };
    show(!closed);
    button.addEventListener('click',()=>{ const expanded=button.getAttribute('aria-expanded')!=='true'; show(expanded); try { localStorage.setItem(key,expanded?'open':'closed'); } catch { /* Navigation remains usable. */ } });
    // Page activation may run after the shell is initialized.
    new MutationObserver(()=>{ if(body.querySelector('.is-active')) show(true); }).observe(body,{subtree:true,attributes:true,attributeFilter:['class']});
  });
}
