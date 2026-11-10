'use strict';
(() => {
 const form=document.getElementById('bombas-form');if(!form)return;
 const search=document.getElementById('bombas-search');
 const onlySelected=document.getElementById('bombas-only-selected');
 const rows=[...form.querySelectorAll('[data-bomba-row]')];
 const update=()=>{
  const term=search.value.trim().toLocaleLowerCase('pt-BR');let selected=0;
  rows.forEach(row=>{
   const checked=row.querySelector('input[type=checkbox]').checked;
   if(checked)selected++;
   row.hidden=!row.dataset.search.includes(term)||(onlySelected.checked&&!checked);
  });
  document.getElementById('bombas-selection-count').textContent=`Seleção antes de adicionar a lista colada: ${selected} em Bombas · ${rows.length-selected} em Redutores.`;
 };
 search.addEventListener('input',update);form.addEventListener('change',update);update();
 form.addEventListener('submit',()=>{
  const boxes=[...form.querySelectorAll('input[name="ordens[]"]')];
  // One JSON field avoids PHP max_input_vars silently truncating a large selection.
  document.getElementById('bombas-selection-json').value=JSON.stringify(boxes.filter(box=>box.checked).map(box=>box.value));
  boxes.forEach(box=>{box.disabled=true;});
 });
})();
