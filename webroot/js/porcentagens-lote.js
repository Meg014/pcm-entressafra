(() => {
 const root=document.getElementById('porcentagens-lote');if(!root)return;
 const filters=document.getElementById('porcentagens-filtros');
 filters.elements.area_id.addEventListener('change',()=>{filters.elements.os.value='';filters.requestSubmit();});
 filters.elements.os.addEventListener('input',()=>{if(filters.elements.os.value.trim())filters.elements.area_id.value='';});
 const form=document.getElementById('porcentagens-form');if(!form)return;
 const button=document.getElementById('porcentagens-salvar'),error=document.getElementById('porcentagens-erro'),counter=document.getElementById('porcentagens-pendentes');
 const inputs=[...root.querySelectorAll('[data-percentage]')],storage=root.dataset.storage;
 const controls=[...form.querySelectorAll('[data-percentage],[data-correction-reason],[data-confirm-reduction]')];
 const keyOf=input=>input.dataset.id+':'+input.dataset.reference;
 const numeric=value=>value===''?null:Number(String(value).replace(',','.'));
 const newKey=()=>crypto.randomUUID?crypto.randomUUID():Array.from(crypto.getRandomValues(new Uint8Array(16)),v=>v.toString(16).padStart(2,'0')).join('');
 let draft={key:form.dataset.key,rows:{}},busy=false,internal=false,storageOK=true;
 try{const saved=sessionStorage.getItem(storage);if(saved)draft=JSON.parse(saved);}catch(_){error.textContent='Não foi possível recuperar o rascunho. Mantenha esta página aberta até salvar.';storageOK=false;}
 if(!draft||!draft.rows||typeof draft.rows!=='object'||!draft.key){error.textContent='Rascunho inválido. Solicite conferência antes de continuar.';return;}
 for(const row of JSON.parse(form.dataset.pending||'[]'))draft.rows[row.atividade_id+':'+row.referencia]=row;
 const remember=()=>{
  try{sessionStorage.setItem(storage,JSON.stringify(draft));storageOK=true;}catch(_){storageOK=false;error.textContent='Não foi possível guardar o rascunho entre páginas. Salve antes de navegar.';}
  const n=Object.keys(draft.rows).length;counter.textContent=n?`${n} célula(s) alterada(s), incluindo outras páginas.`:'Nenhuma alteração pendente.';button.disabled=busy||!n;
 };
 const decorate=input=>{
  const row=draft.rows[keyOf(input)],cell=input.closest('[data-cell]'),details=cell.querySelector('details');cell.classList.toggle('cell-dirty',!!row);
  if(!row){details.hidden=true;return;}
  const value=numeric(row.percentual),base=numeric(input.dataset.base);
  const sameOrder=inputs.filter(other=>other.dataset.id===input.dataset.id),index=sameOrder.indexOf(input);
  let previous=0,next=null;
  for(let i=0;i<index;i++){const v=numeric(sameOrder[i].value);if(v!==null)previous=v;}
  for(let i=index+1;i<sameOrder.length;i++){const v=numeric(sameOrder[i].value);if(v!==null){next=v;break;}}
  const exceptional=value!==null&&(value<previous||(base!==null&&value<base)||(next!==null&&value>next));
  details.hidden=!(exceptional||row.confirmar_reducao||row.motivo||details.dataset.serverError);
  if(exceptional)details.open=true;
 };
 inputs.forEach(input=>{
  const cell=input.closest('[data-cell]'),details=cell.querySelector('details'),reason=cell.querySelector('[data-correction-reason]'),confirmation=cell.querySelector('[data-confirm-reduction]');
  const saved=draft.rows[keyOf(input)];if(saved){input.value=saved.percentual;reason.value=saved.motivo||'';confirmation.checked=!!saved.confirmar_reducao;}
  const update=()=>{
   const value=input.value.trim(),base=numeric(input.dataset.base),cellKey=keyOf(input);
   if(value===''&&base===null||value!==''&&numeric(value)===base){delete draft.rows[cellKey];input.removeAttribute('aria-invalid');}
   else draft.rows[cellKey]={atividade_id:Number(input.dataset.id),referencia:input.dataset.reference,percentual:value,base,base_registro:Number(input.dataset.record),revisao:Number(input.dataset.revision),motivo:reason.value,confirmar_reducao:confirmation.checked,os_numero:input.dataset.os,semana:Number(input.dataset.week)};
   draft.key=newKey();inputs.filter(other=>other.dataset.id===input.dataset.id).forEach(decorate);remember();
  };
  input.addEventListener('input',update);reason.addEventListener('input',update);confirmation.addEventListener('change',update);
  input.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();const next=inputs[inputs.indexOf(input)+1];if(next){next.focus();next.select();}}});
 });
 inputs.forEach(decorate);remember();
 root.querySelectorAll('[data-batch-page]').forEach(link=>link.addEventListener('click',event=>{if(busy||!storageOK){event.preventDefault();error.textContent='Salve antes de navegar.';}else internal=true;}));
 filters.addEventListener('submit',event=>{if(busy||!storageOK){event.preventDefault();error.textContent='Aguarde o salvamento antes de navegar.';}});
 window.addEventListener('beforeunload',event=>{if(Object.keys(draft.rows).length&&!internal){event.preventDefault();event.returnValue='';}});
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(busy)return;const rows=Object.values(draft.rows);if(!rows.length)return;
  const invalid=rows.find(row=>!/^\d+(?:[.,]\d{1,2})?$/.test(String(row.percentual))||numeric(row.percentual)>100);
  if(invalid){error.textContent=`O.S. ${invalid.os_numero||invalid.atividade_id} · Semana ${invalid.semana||invalid.referencia}: informe de 0 a 100. Campo vazio não apaga lançamento salvo.`;return;}
  busy=true;remember();error.textContent='';controls.forEach(input=>input.disabled=true);
  document.getElementById('porcentagens-chave').value=draft.key;document.getElementById('porcentagens-json').value=JSON.stringify(rows);
  try{
   const response=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':form.elements._csrfToken.value}});
   if(response.redirected)throw Error('Entre novamente para retomar o lançamento.');
   if(response.status===403)throw Error('Sem autorização para salvar ou sessão expirada.');
   const result=await response.json();if(!response.ok||!result.ok)throw Error(result.error||'Não foi possível salvar.');
   sessionStorage.removeItem(storage);draft.rows={};internal=true;location.reload();
  }catch(failure){error.textContent=failure.message+' As alterações digitadas foram mantidas.';inputs.forEach(input=>{if(draft.rows[keyOf(input)]){input.setAttribute('aria-invalid','true');const details=input.closest('[data-cell]').querySelector('details');details.dataset.serverError='1';decorate(input);}});}
  finally{busy=false;controls.forEach(input=>input.disabled=false);remember();}
 });
})();
