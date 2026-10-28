'use strict';
(() => {
 const svg=document.querySelector('#curva-semanal .curve-chart');if(!svg)return;
 const labels=[...svg.querySelectorAll('[data-point-label]')];
 const overlaps=(a,b)=>a.x<b.x+b.width+4&&a.x+a.width+4>b.x&&a.y<b.y+b.height+4&&a.y+a.height+4>b.y;
 const arrange=()=>{
  const placed=[],width=svg.viewBox.baseVal.width;
  labels.forEach(label=>{
   const anchorX=Number(label.dataset.pointX),anchorY=Number(label.dataset.pointY);
   const connector=document.getElementById(label.dataset.connector);
   label.style.visibility='visible';let found=false;
   for(const dy of [-14,21,-34,41,-54,61,-74,81,-94,101]){
    for(const dx of [0,-14,14,-28,28]){
     label.setAttribute('x',String(anchorX+dx));label.setAttribute('y',String(anchorY+dy));
     const box=label.getBBox();
     if(box.x<38||box.x+box.width>width-18||box.y<20||box.y+box.height>363||placed.some(previous=>overlaps(box,previous)))continue;
     placed.push({x:box.x,y:box.y,width:box.width,height:box.height});found=true;
     connector.setAttribute('x2',String(anchorX+dx));connector.setAttribute('y2',String(anchorY+dy+(dy<0?4:-10)));
     connector.style.visibility=Math.abs(dy)>21||dx!==0?'visible':'hidden';break;
    }
    if(found)break;
   }
   if(!found){label.style.visibility='hidden';connector.style.visibility='hidden';}
  });
  svg.dataset.labelsReady='1';
 };
 let pending=false;const schedule=()=>{if(pending)return;pending=true;requestAnimationFrame(()=>{pending=false;arrange();});};
 schedule();window.addEventListener('resize',schedule);
 if(document.fonts&&document.fonts.ready)document.fonts.ready.then(schedule);
 if(typeof ResizeObserver!=='undefined')new ResizeObserver(schedule).observe(svg);
})();
