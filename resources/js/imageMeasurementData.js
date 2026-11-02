const clamp = n => Math.max(0, Math.min(1, n));
export function fittedImageRect(width,height,rect) {
  if (!(width>0 && height>0 && rect.width>0 && rect.height>0)) throw new Error('Image dimensions are unavailable.');
  const scale=Math.min(rect.width/width,rect.height/height);
  return {left:rect.left+(rect.width-width*scale)/2,top:rect.top+(rect.height-height*scale)/2,width:width*scale,height:height*scale};
}
export function imagePoint(clientX,clientY,rect) {
  return {x:clamp((clientX-rect.left)/rect.width),y:clamp((clientY-rect.top)/rect.height)};
}
export function imageDistance(a,b,width,height) { return Math.hypot((b.x-a.x)*width,(b.y-a.y)*height); }
export function measuredSegment(a,b,width,height,calibration,approved,imageHash,target='body') {
  const pixels=imageDistance(a,b,width,height);
  const valid=approved && calibration && calibration.imageSha256===imageHash && calibration.width===width && calibration.height===height &&
    ['photograph','illustrative_estimate'].includes(calibration.basis) && (target==='body'||calibration.scope==='parts') &&
    Number.isFinite(calibration.lengthMm) && calibration.lengthMm>0;
  if (!valid) return {value:100*pixels/width,unit:'% image width',label:'Relative image distance · no approved physical scale'};
  const reference=imageDistance(calibration.a,calibration.b,width,height);
  if (!(reference>=1)) return {value:100*pixels/width,unit:'% image width',label:'Relative image distance · invalid calibration'};
  return {value:pixels/reference*calibration.lengthMm,unit:'mm',label:calibration.basis==='photograph'?'Calibrated image-plane distance':'Illustrative estimate · not an actual specimen measurement'};
}
