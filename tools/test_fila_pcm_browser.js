(() => {
 const results=[];const check=(value,message)=>{if(!value)throw Error(message);results.push(message);};
 try{
  const hours=document.getElementById('horas-controle'),supplies=document.getElementById('insumos-controle');
  check(!!hours&&!!supplies,'Duas seções na mesma página');
  check(document.querySelector('h1').textContent.includes('MOENDA'),'Cabeçalho identifica O.S. e setor');
  check(document.querySelector('main').textContent.includes('Percentual atual de evolução:'),'Evolução visível');
  check(hours.querySelector('[name=categoria]').value==='horas'&&supplies.querySelector('[name=categoria]').value==='insumos','Categorias independentes');
  check(!document.querySelector('form form'),'Formulários separados sem aninhamento');
  const h=hours.querySelector('[name="selecionados[]"]'),s=supplies.querySelector('[name="selecionados[]"]');
  h.click();check(h.checked&&!s.checked,'Selecionar horas não seleciona insumos');
  check(new FormData(hours).getAll('selecionados[]').length===1&&new FormData(supplies).getAll('selecionados[]').length===0,'Envio de horas contém só sua seleção');
  s.click();h.click();check(!h.checked&&s.checked,'Selecionar insumos não seleciona horas');
  check(new FormData(supplies).get('categoria')==='insumos','Envio de insumos preserva categoria');
  check(!hours.checkValidity()&&!supplies.checkValidity(),'Cada confirmação exige consentimento');
  supplies.querySelector('[type=checkbox][name=confirmar]').click();check(supplies.checkValidity()&&!hours.checkValidity(),'Consentimento de insumos não confirma horas');
  supplies.querySelector('select[name=acao]').value='pendente';check(hours.querySelector('select[name=acao]').value==='lancado','Operações de confirmar e reabrir independentes');
  check(document.getElementById('horas').textContent.includes('Editar hora'),'Ação administrativa presente');
  check(document.getElementById('historico').textContent.includes('Recebimento de insumo')&&document.getElementById('historico').textContent.includes('Recebimento de apontamento'),'Históricos de recebimento na página');
  check(!supplies.querySelector('thead').textContent.includes('Horas')&&supplies.querySelector('thead').textContent.includes('Quantidade'),'Quantidades separadas de horas');
  const ids=[...document.querySelectorAll('[id]')].map(element=>element.id);check(ids.length===new Set(ids).size,'IDs únicos entre as categorias');
  check(document.documentElement.scrollWidth<=window.innerWidth,'Sem overflow horizontal');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:window.innerWidth,results,errors:window.errors});document.body.append(output);
})();
