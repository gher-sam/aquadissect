import { fittedImageRect, imagePoint, measuredSegment } from './imageMeasurementData.js';

export class ImageMeasurement {
  constructor(frame,manifest,getImage) {
    this.frame=frame;this.manifest=manifest;this.getImage=getImage;this.points=[{x:.3,y:.5},{x:.7,y:.5}];this.key='natural';
    this.root=document.createElement('div');this.root.className='image-measurement';
    this.root.innerHTML=`<div class="measurement-controls"><button type="button" data-toggle aria-pressed="false">Measure</button><div data-controls hidden><p data-result role="status" aria-live="polite"></p><details><summary>Measurement options</summary><label>Target <select data-target><option value="body">Body</option><option value="parts">Anatomical part</option></select></label><label>Unit <select data-unit><option value="mm">mm</option><option value="cm">cm</option></select></label><button type="button" data-reset>Reset endpoints</button></details><small>Drag endpoints or focus them and use arrow keys. Measure only landmarks supported by the reference.</small></div></div><div class="measurement-overlay" hidden><svg aria-hidden="true"><line stroke="#0140c1" stroke-width="3"/></svg><button type="button" class="measure-endpoint" data-point="0" aria-label="Measurement start; arrow keys move">A</button><button type="button" class="measure-endpoint" data-point="1" aria-label="Measurement end; arrow keys move">B</button></div>`;
    frame.append(this.root);
    this.controls=this.root.querySelector('.measurement-controls');
    this.panel=this.frame.closest('.viewer-panel');
    this.panel.classList.add('has-measurement');this.panel.append(this.controls);
    this.overlay=this.root.querySelector('.measurement-overlay');
    this.toggle=this.controls.querySelector('[data-toggle]');
    this.toggle.onclick=()=>{this.enabled=!this.enabled;this.overlay.hidden=!this.enabled;this.controls.querySelector('[data-controls]').hidden=!this.enabled;this.toggle.setAttribute('aria-pressed',String(this.enabled));this.render();};
    this.controls.querySelector('[data-reset]').onclick=()=>{this.points=[{x:.3,y:.5},{x:.7,y:.5}];this.render();};
    this.controls.querySelectorAll('select').forEach(s=>s.onchange=()=>this.render());
    this.root.querySelectorAll('[data-point]').forEach(button=>{
      button.onpointerdown=e=>{e.stopPropagation();button.setPointerCapture(e.pointerId);this.active=Number(button.dataset.point);};
      button.onpointermove=e=>{if(this.active!==Number(button.dataset.point)||!button.hasPointerCapture(e.pointerId))return;e.preventDefault();this.points[this.active]=imagePoint(e.clientX,e.clientY,this.rect());this.render();};
      button.onpointerup=()=>{this.active=null;};
      button.onpointercancel=()=>{this.active=null;};
      button.onkeydown=e=>{const directions={ArrowLeft:[-.005,0],ArrowRight:[.005,0],ArrowUp:[0,-.005],ArrowDown:[0,.005]};if(!directions[e.key])return;e.preventDefault();e.stopPropagation();const p=this.points[Number(button.dataset.point)],d=directions[e.key];p.x=Math.max(0,Math.min(1,p.x+d[0]));p.y=Math.max(0,Math.min(1,p.y+d[1]));this.render();};
    });
    this.resize=new ResizeObserver(()=>this.render());this.resize.observe(frame);
    this.getImage(this.key)?.addEventListener('load',()=>this.render());
  }
  rect() { const image=this.getImage(this.key);return fittedImageRect(image.naturalWidth,image.naturalHeight,this.frame.getBoundingClientRect()); }
  update(key) { if(this.key!==key){this.key=key;this.points=[{x:.3,y:.5},{x:.7,y:.5}];}this.render(); }
  render() {
    if(!this.enabled)return;
    const image=this.getImage(this.key);if(!image?.naturalWidth)return;
    const base=this.frame.getBoundingClientRect();if(!(base.width>0 && base.height>0))return;
    const r=this.rect();
    const xy=this.points.map(p=>({x:r.left-base.left+p.x*r.width,y:r.top-base.top+p.y*r.height}));
    this.root.querySelectorAll('[data-point]').forEach((el,i)=>{el.style.left=xy[i].x+'px';el.style.top=xy[i].y+'px';});
    const line=this.root.querySelector('line');for(const [key,value]of Object.entries({x1:xy[0].x,y1:xy[0].y,x2:xy[1].x,y2:xy[1].y}))line.setAttribute(key,value);
    const result=measuredSegment(...this.points,image.naturalWidth,image.naturalHeight,this.manifest.calibrations?.[this.key],this.manifest.enhancementsApproved===true,this.manifest.assetRecords?.[this.key]?.sha256,this.controls.querySelector('[data-target]').value);
    const cm=result.unit==='mm'&&this.controls.querySelector('[data-unit]').value==='cm';
    this.controls.querySelector('[data-result]').textContent=`${(cm?result.value/10:result.value).toFixed(2)} ${cm?'cm':result.unit} · ${result.label}`;
  }
  destroy(){this.resize.disconnect();this.controls.remove();this.panel.classList.remove('has-measurement');this.root.remove();}
}
