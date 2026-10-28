window.addEventListener('load',()=>setTimeout(async()=>{
 const results=[],check=(ok,message)=>{if(!ok)throw Error(message);results.push(message);};
 try{
  const root=document.getElementById('porcentagens-lote'),form=document.getElementById('porcentagens-form'),inputs=[...root.querySelectorAll('[data-percentage]')],headers=[...root.querySelectorAll('[data-week-header]')];
  check(headers.length===3,'Somente três semanas iniciadas');
  check(headers[0].textContent.includes('14/10/26 a 19/10/26')&&headers[2].textContent.includes('27/10/26 a 02/11/26'),'Datas reais de semanas parciais e virada de mês');
  check(root.querySelectorAll('thead .week-current').length===1&&headers[2].classList.contains('week-current'),'Semana atual destacada');
  check(inputs.length===6&&inputs.every(el=>el.value===''),'Células sem lançamento permanecem vazias');
  check(root.querySelectorAll('#porcentagens-salvar').length===1&&document.getElementById('porcentagens-salvar').disabled,'Um único botão de salvar inicialmente inativo');
  const edit=(input,value)=>{input.value=value;input.dispatchEvent(new Event('input',{bubbles:true}));};
  edit(inputs[0],'10');edit(inputs[1],'30');edit(inputs[2],'50');edit(inputs[3],'0');
  check(root.querySelectorAll('.cell-dirty').length===4,'Células alteradas destacadas');
  let draft=JSON.parse(sessionStorage.getItem(root.dataset.storage));
  check(Object.keys(draft.rows).length===4&&Object.values(draft.rows).some(r=>r.percentual==='0'&&r.base===null),'Zero explícito é diferente de célula vazia');
  edit(inputs[0],'');check(Object.keys(JSON.parse(sessionStorage.getItem(root.dataset.storage)).rows).length===3,'Vazio novo não produz lançamento artificial');edit(inputs[0],'10');
  edit(inputs[2],'20');const cell=inputs[2].closest('[data-cell]'),details=cell.querySelector('details');
  check(!details.hidden&&details.open,'Redução entre semanas exige correção excepcional');
  const reason=cell.querySelector('textarea'),confirm=cell.querySelector('input[type=checkbox]');reason.value='Correção de digitação';reason.dispatchEvent(new Event('input',{bubbles:true}));confirm.checked=true;confirm.dispatchEvent(new Event('change',{bubbles:true}));
  let calls=0,payload;window.fetch=async(url,options)=>{calls++;payload=JSON.parse(options.body.get('alteracoes_json'));return {ok:false,status:422,redirected:false,json:async()=>({error:'O.S. 005300 · Semana 2: outra pessoa alterou esta célula.'})};};
  edit(inputs[1],'101');form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));
  check(calls===0&&document.getElementById('porcentagens-erro').textContent.includes('Semana 2'),'Valor inválido indica O.S. e semana sem enviar');
  edit(inputs[1],'30');form.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));await new Promise(resolve=>setTimeout(resolve,40));
  check(calls===1&&payload.length===4&&new Set(payload.map(r=>r.atividade_id+':'+r.referencia)).size===4,'Lote envia somente células alteradas de várias semanas');
  check(payload.every(r=>r.base_registro===0&&r.revisao===0),'Revisão e identidade original acompanham cada célula');
  check(payload.some(r=>r.confirmar_reducao&&r.motivo==='Correção de digitação'),'Confirmação e justificativa acompanham a correção');
  check(inputs[0].value==='10'&&inputs[1].value==='30'&&inputs[2].value==='20'&&inputs[3].value==='0','Erro do backend preserva todos os valores digitados');
  check(!inputs[0].disabled&&!reason.disabled&&document.getElementById('porcentagens-erro').textContent.includes('mantidas'),'Formulário volta a permitir edição após conflito');
  check(Object.keys(JSON.parse(sessionStorage.getItem(root.dataset.storage)).rows).length===4,'Rascunho sobrevive ao erro');
  const scroll=root.querySelector('.weekly-sheet-scroll');check(getComputedStyle(scroll).overflowX==='auto','Rolagem horizontal habilitada');
  check(getComputedStyle(root.querySelector('.fixed-os')).position==='sticky','O.S. permanece fixa');
  check(getComputedStyle(headers[0]).position==='sticky','Cabeçalhos permanecem fixos');
  check(document.documentElement.scrollWidth<=innerWidth,'Página sem overflow horizontal');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:innerWidth,results,errors:window.errors});document.body.append(output);
},100));
