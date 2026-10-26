'use strict';
(() => {
 const form=document.getElementById('batch-form'); if(!form)return;
 const orderStatus=document.getElementById('entry-order-status');
 const setOrder=order=>{
  orderStatus.hidden=!order;
  orderStatus.textContent=order?'Percentual atual da O.S.: '+Number(order.percentual_atual??0).toLocaleString('pt-BR')+'%':'';
 };
 document.getElementById('os-autocomplete').addEventListener('pcm-entry-order',event=>setOrder(event.detail));
 setOrder(JSON.parse(orderStatus.dataset.order||'null'));
 const lines=document.getElementById('entry-lines'); let serial=lines.children.length;
 const number=()=>{document.getElementById('entry-count').value=lines.children.length;[...lines.children].forEach((row,i)=>{row.querySelector('.entry-number span').textContent=i+1;});};
 const init=row=>{
  window.initEmployeeAutocomplete(row.querySelector('[data-lookup]'));
  const fields=[...row.querySelectorAll('[data-mask]')], [date,start,end]=fields;
  const validDate=v=>{const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(v);if(!m||+m[3]<1)return false;const d=new Date(0);d.setUTCFullYear(+m[3],+m[2]-1,+m[1]);return d.getUTCFullYear()===+m[3]&&d.getUTCMonth()===+m[2]-1&&d.getUTCDate()===+m[1];};
  const time=v=>/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(v);
  const minutes=v=>+v.slice(0,2)*60 + +v.slice(3);
  const update=()=>{
   date.setCustomValidity(date.value&&!validDate(date.value)?'Informe uma data real no formato DD/MM/AAAA.':'');
   [start,end].forEach(f=>f.setCustomValidity(f.value&&!time(f.value)?'Informe uma hora válida entre 00:00 e 23:59.':''));
   let output='';
   if(validDate(date.value)&&time(start.value)&&time(end.value)){
    const duration=minutes(end.value)-minutes(start.value);
    if(duration<=0){end.setCustomValidity('A hora final deve ser maior que a inicial, na mesma data.');output='Intervalo inválido.';}
    else output='Duração: '+Math.floor(duration/60)+'h '+duration%60+'min';
   }
   row.querySelector('.entry-duration').textContent=output;
   end.title=output;
  };
  fields.forEach(f=>f.addEventListener('input',()=>{
   const digits=v=>v.replace(/\D/g,''); const before=digits(f.value.slice(0,f.selectionStart)).length;
   const v=digits(f.value).slice(0,f.dataset.mask==='date'?8:4);
   f.value=v.slice(0,2)+(v.length>2?(f.dataset.mask==='date'?'/':':')+v.slice(2, f.dataset.mask==='date'?4:4):'')+(f.dataset.mask==='date'&&v.length>4?'/'+v.slice(4):'');
   let caret=0,count=0;while(caret<f.value.length&&count<before){if(/\d/.test(f.value[caret]))count++;caret++;}f.setSelectionRange(caret,caret);update();
  }));
  row.querySelector('.remove-entry').addEventListener('click',()=>{row.remove();if(!lines.children.length)add();number();});
  update();
 };
 const add=()=>{
  const template=document.getElementById('entry-template');
  const holder=document.createElement('div');holder.innerHTML=template.innerHTML.replaceAll('__INDEX__',String(serial++));
  const row=holder.firstElementChild;lines.append(row);init(row);number();row.querySelector('input').focus();
 };
 [...lines.children].forEach(init);
 document.getElementById('add-entry').addEventListener('click',add);
 form.addEventListener('submit',event=>{
  // Reindex after removals, so the server can attribute errors to visible lines.
  [...lines.children].forEach((row,i)=>row.querySelectorAll('[name]').forEach(f=>{f.name=f.name.replace(/^apontamentos\[[^\]]+\]/,'apontamentos['+i+']');}));
 });
})();
