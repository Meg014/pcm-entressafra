(async()=>{
 const results=[];const check=(value,message)=>{if(!value)throw Error(message);results.push(message);};
 try{
  const lines=document.getElementById('supplies-lines'),add=document.getElementById('supplies-add');
  check(lines.children.length===1,'Uma linha inicial');
  const first=lines.firstElementChild;first.querySelector('[name$="[material]"]').value='Material preservado';
  const date=first.querySelector('[data-supply-date]');date.value='26102026';date.dispatchEvent(new Event('input'));
  check(date.value==='26/10/2026'&&date.checkValidity(),'Máscara e data válida');
  date.value='31022026';date.dispatchEvent(new Event('input'));check(!date.checkValidity(),'Data inexistente bloqueada');
  date.value='26102026';date.dispatchEvent(new Event('input'));
  const quantity=first.querySelector('[data-supply-quantity]');quantity.value='0';quantity.dispatchEvent(new Event('input'));check(!quantity.checkValidity(),'Zero bloqueado');
  quantity.value='2,5';quantity.dispatchEvent(new Event('input'));check(quantity.checkValidity(),'Quantidade fracionada aceita');
  add.click();add.click();check(lines.children.length===3,'Adiciona várias linhas');
  check(first.querySelector('[name$="[material]"]').value==='Material preservado','Preserva material ao adicionar');
  const second=lines.children[1],lookup=second.querySelector('[data-lookup]'),input=lookup.querySelector('[role=combobox]');
  input.value='teste';input.dispatchEvent(new Event('input'));await new Promise(resolve=>setTimeout(resolve,400));
  const options=lookup.querySelectorAll('[role=option]');check(options.length===2&&options[1].disabled,'Funcionário não NORMAL bloqueado');options[0].click();
  check(lookup.querySelector('[id$=funcionario-chave]').value==='0001','Seleciona funcionário confirmado');
  check(first.querySelector('[id$=funcionario-chave]').value==='','Funcionários independentes por linha');
  lines.lastElementChild.querySelector('.supplies-remove').click();check(lines.children.length===2,'Remove linha');
  check(first.querySelector('[name$="[material]"]').value==='Material preservado','Remover preserva campos');
  document.getElementById('atividade-selecionada').value='1';first.querySelector('[id$=funcionario-chave]').value='0001';
  const submit=new Event('submit',{cancelable:true});document.getElementById('insumos-form').dispatchEvent(submit);
  check(!submit.defaultPrevented,'Linha removida não bloqueia envio');
  check(document.getElementById('supplies-count').value==='2','Quantidade de linhas correta');
  const ids=[...document.querySelectorAll('[id]')].map(element=>element.id);check(ids.length===new Set(ids).size,'IDs únicos');
  check(document.documentElement.scrollWidth<=window.innerWidth,'Sem overflow horizontal');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:window.innerWidth,results,errors:window.errors});document.body.append(output);
})();
