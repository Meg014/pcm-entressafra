'use strict';
window.addEventListener('load',()=>{
 const output=document.createElement('pre');output.id='mecanica-browser-results';
 try{
  const check=(value,message)=>{if(!value)throw Error(message);};
  const form=document.getElementById('bombas-form');
  const rows=[...form.querySelectorAll('[data-bomba-row]')];
  check(rows.length===4,'Todas as O.S. disponíveis');
  const boxes=rows.map(row=>row.querySelector('input[type=checkbox]'));
  boxes[0].checked=true;boxes[0].dispatchEvent(new Event('change',{bubbles:true}));
  const search=document.getElementById('bombas-search');
  search.value='000002';search.dispatchEvent(new Event('input'));
  check(rows[0].hidden&&!rows[1].hidden&&boxes[0].checked,'Busca preserva a seleção oculta');
  boxes[1].checked=true;boxes[1].dispatchEvent(new Event('change',{bubbles:true}));
  search.value='';search.dispatchEvent(new Event('input'));
  const onlySelected=document.getElementById('bombas-only-selected');
  onlySelected.checked=true;onlySelected.dispatchEvent(new Event('change',{bubbles:true}));
  check(rows.filter(row=>!row.hidden).length===2,'Filtro de Bombas selecionados');
  check(document.getElementById('bombas-selection-count').textContent.includes('2 em Bombas'),'Contagens da seleção');
  form.addEventListener('submit',event=>event.preventDefault());
  form.dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}));
  const submitted=new FormData(form);
  check(submitted.get('grupo_manual')==='bombas','Regra manual explícita de Bombas');
  const ids=JSON.parse(submitted.get('selecao_json'));
  check(JSON.stringify(ids)===JSON.stringify([boxes[0].value,boxes[1].value]),'IDs completos na seleção JSON');
  check(submitted.getAll('ordens[]').length===0&&submitted.get('lista_completa')==='1','Envio sem truncamento por max_input_vars');
  check(document.documentElement.scrollWidth<=window.innerWidth,'Sem overflow horizontal');
  output.textContent='PASS: busca, seleção múltipla, contagens, filtro de Bombas e envio integral JSON.';
 }catch(error){output.textContent='FAIL: '+error.message;}
 document.body.append(output);
});
