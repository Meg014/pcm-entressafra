window.addEventListener('load',()=>setTimeout(()=>{
 const results=[],check=(ok,message)=>{if(!ok)throw Error(message);results.push(message);};
 try{
  const root=document.getElementById('transferencias-lista'),form=document.getElementById('transferencias-selecao'),dialog=document.getElementById('transferencias-confirmacao');
  check(!!root&&!!form,'Transferências integradas à prévia');
  check(root.textContent.includes('Caderno atual')&&root.textContent.includes('Caderno de destino'),'Origem e destino apresentados');
  check(root.textContent.includes('000001')&&root.textContent.includes('MOENDA')&&root.textContent.includes('CALDEIRARIA - MOENDA'),'O.S. e cadernos reais da fixture identificados');
  check(root.textContent.includes('Selecionar todas')&&root.textContent.includes('Manter no caderno original'),'Ações de selecionar todas e manter origem');
  check(dialog.open&&dialog.matches(':modal'),'Confirmação aberta em janela modal');
  check(dialog.textContent.includes('1 O.S.')&&dialog.textContent.includes('MOENDA'),'Quantidade e destino explícitos na confirmação');
  check(dialog.querySelector('input[name=assinatura]').value.length===64,'Confirmação contém assinatura da seleção');
  check(dialog.querySelector('button[name=confirmar_transferencia]').value==='1','Confirmação exige ação explícita');
  check(dialog.querySelector('input[name=justificativa]').required,'Justificativa preservada');
  dialog.querySelector('[data-transfer-cancel]').click();check(!dialog.open,'Cancelar fecha a confirmação sem enviar');
  const box=form.querySelector('input[type=checkbox]');box.checked=false;box.dispatchEvent(new Event('change',{bubbles:true}));
  check(document.querySelector('[data-transfer-total]').textContent==='0'&&document.getElementById('transferencias-revisar').disabled,'Contagem atualiza e impede revisar seleção vazia');
  box.checked=true;box.dispatchEvent(new Event('change',{bubbles:true}));
  check(document.querySelector('[data-transfer-total]').textContent==='1'&&!document.getElementById('transferencias-revisar').disabled,'Seleção pode ser refeita sem transferir');
  check(document.documentElement.scrollWidth<=innerWidth,'Página sem overflow horizontal');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:innerWidth,results,errors:window.errors});document.body.append(output);
},100));
