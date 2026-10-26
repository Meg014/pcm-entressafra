(async()=>{
 const results=[],check=(value,message)=>{if(!value)throw Error(message);results.push(message);};
 const wait=()=>new Promise(resolve=>setTimeout(resolve,320));
 const input=(field,text)=>{field.focus();field.value=text;field.dispatchEvent(new Event('input',{bubbles:true}));};
 const key=(field,value)=>{const event=new KeyboardEvent('keydown',{key:value,bubbles:true,cancelable:true});field.dispatchEvent(event);return event.defaultPrevented;};
 try{
  const lines=document.getElementById('entry-lines'),add=document.getElementById('add-entry');
  const field=lines.firstElementChild.querySelector('textarea'),list=lines.firstElementChild.querySelector('.service-options');
  const calls=[];let fail=false,empty=false;
  window.fetch=async url=>{calls.push(String(url));return {ok:!fail,json:async()=>({results:empty?[]:[{id:1,descricao:'DESMONTAGEM'},{id:2,descricao:'DESMONTAGEM E MONTAGEM'}]})};};
  input(field,'D');await wait();check(calls.length===0,'Não busca antes de dois caracteres');
  add.click();const second=lines.lastElementChild.querySelector('textarea');
  input(field,'DES');await wait();check(!list.hidden&&list.children.length===2,'Sugestões abaixo do campo');
  check(second.value===''&&lines.lastElementChild.querySelector('.service-options').hidden,'Linhas independentes');
  check(lines.lastElementChild.getBoundingClientRect().top>=list.getBoundingClientRect().bottom,'Sugestões não sobrepõem a próxima linha');
  check(key(field,'ArrowDown')&&key(field,'Enter'),'Setas e Enter selecionam');
  check(field.value==='DESMONTAGEM'&&list.hidden,'Seleciona o texto e fecha a lista');
  input(field,field.value+' E AJUSTE PERSONALIZADO');check(field.value.endsWith('PERSONALIZADO'),'Texto editável depois da seleção');
  key(field,'Escape');
  input(second,'DES');await wait();const secondList=lines.lastElementChild.querySelector('.service-options');
  check(!secondList.hidden,'Autocomplete na linha adicionada pelo +');
  const option=secondList.children[1];option.dispatchEvent(new PointerEvent('pointerdown',{bubbles:true,cancelable:true}));option.click();
  check(second.value==='DESMONTAGEM E MONTAGEM','Seleção por clique ou toque');
  input(second,'DES');await wait();check(!key(second,'Enter'),'Enter sem opção ativa mantém a digitação livre');
  key(second,'Escape');check(secondList.hidden,'Escape fecha sugestões');
  empty=true;input(second,'DESCRIÇÃO SEM CORRESPONDÊNCIA');await wait();
  check(secondList.hidden&&second.validity.valid&&second.value==='DESCRIÇÃO SEM CORRESPONDÊNCIA','Ausência de resultados mantém texto livre');
  fail=true;input(second,'SERVIÇO FORA DO CATÁLOGO');await wait();
  check(second.value==='SERVIÇO FORA DO CATÁLOGO'&&second.validity.valid,'Falha da pesquisa não impede texto livre');
  const long='  '+('DESCRIÇÃO PERSONALIZADA\n'.repeat(80))+'  ';input(second,long);await wait();
  check(second.value===long&&second.validity.valid,'Texto longo e espaços preservados');
  check(!document.querySelector('[name*=servico_sugerido_id]'),'Nenhum vínculo de catálogo obrigatório');
  check(document.documentElement.scrollWidth<=window.innerWidth+1,'Sem rolagem horizontal na tela');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:innerWidth,results,errors:window.errors});document.body.append(output);
})();
