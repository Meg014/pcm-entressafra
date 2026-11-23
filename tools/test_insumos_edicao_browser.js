(async()=>{
 const results=[];const check=(value,message)=>{if(!value)throw Error(message);results.push(message);};
 try{
  const button=document.querySelector('[data-edit-supply][data-unit=""]');
  const dialog=document.getElementById('insumo-edicao');
  button.click();
  check(dialog.open,'Abre modal de edição');
  check(dialog.querySelector('[data-edit-os]').textContent===button.dataset.os,'O.S. preservada como texto');
  check(!dialog.querySelector('input[name="os"]')&&!dialog.querySelector('input[name="data"]'),'Identificação somente leitura');
  check(document.getElementById('edit-unit').value==='','Registro antigo sem unidade');
  check(document.getElementById('edit-reason').value==='Complementação da unidade de medida','Justificativa para complemento da unidade');
  check(document.getElementById('edit-material').value===button.dataset.material,'Material original carregado');
  check(!document.getElementById('edit-awareness').required,'Pendente dispensa ciência TOTVS');
  document.getElementById('edit-unit').value='kg';
  check(document.getElementById('insumo-edicao-form').checkValidity(),'Formulário válido com unidade');
  check(new URL(document.getElementById('insumo-edicao-form').action).pathname.includes('/insumos/edit/'),'Envio para edição administrativa');
  document.getElementById('edit-cancel').click();check(!dialog.open,'Cancelar fecha modal');
  button.dataset.confirmed='1';button.click();
  check(!document.getElementById('edit-confirmed').hidden&&document.getElementById('edit-awareness').required,'Confirmado exige ciência explícita');
  document.getElementById('edit-unit').value='kg';
  check(!document.getElementById('insumo-edicao-form').checkValidity(),'Bloqueia envio sem ciência');
  document.getElementById('edit-awareness').checked=true;
  check(document.getElementById('insumo-edicao-form').checkValidity(),'Aceita ciência explícita');
  check(dialog.getBoundingClientRect().width<=window.innerWidth,'Modal cabe na tela');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:window.innerWidth,results,errors:window.errors});document.body.append(output);
})();
