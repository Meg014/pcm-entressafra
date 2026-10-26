async function testPorcentagensLote(source) {
  const assert=(value,message)=>{if(!value)throw Error(message);};
  const stored=new Map();let serial=0,calls=0,reloads=0,resolveRequest,fail=false;
  const storage={getItem:key=>stored.get(key)||null,setItem:(key,value)=>stored.set(key,value),removeItem:key=>stored.delete(key)};
  function page(id,base,key='user1-period1-area1'){
    const handlers={},input={value:String(base),dataset:{id:String(id),base:String(base),revision:'0'},addEventListener(type,fn){handlers[type]=fn;}},link={addEventListener(type,fn){handlers.page=fn;}};
    const filters={elements:{area_id:{value:'1',addEventListener(type,fn){handlers.sector=fn;}},os:{value:'',addEventListener(type,fn){handlers.search=fn;}}},requestSubmit(){handlers.filterRequested=true;},addEventListener(type,fn){handlers.filter=fn;}};
    const form={dataset:{key:'12345678901234567890123456789012',pending:'[]'},elements:{_csrfToken:{value:'csrf'}},action:'/lancar-porcentagem?area_id=1',addEventListener(type,fn){handlers.submit=fn;}};
    const root={dataset:{storage:key,reference:'2026-10-19'},querySelectorAll:selector=>selector==='[data-percentage]'?[input]:[link]};
    const nodes={'porcentagens-lote':root,'porcentagens-filtros':filters,'porcentagens-form':form,'porcentagens-salvar':{},'porcentagens-erro':{},'porcentagens-pendentes':{},'porcentagens-chave':{},'porcentagens-json':{}};
    const fetch=async(url,options)=>{calls++;assert(options.headers['X-CSRF-Token']==='csrf','CSRF batch');if(fail)throw Error('offline');return new Promise(resolve=>{resolveRequest=resolve;});};
    Function('document','sessionStorage','window','location','URL','crypto','fetch','FormData',source)({getElementById:key=>nodes[key]},storage,{addEventListener(type,fn){handlers.unload=fn;}},{href:'http://localhost/lancar-porcentagem?area_id=1',reload(){reloads++;}},URL,{randomUUID:()=>`12345678-1234-1234-1234-${String(++serial).padStart(12,'0')}`},fetch,class{});
    return {nodes,input,handlers,edit(value){input.value=String(value);handlers.input();}};
  }
  const one=page(1,20);assert(one.input.value==='20'&&calls===0,'last total without automatic save');one.edit(35);
  const filter=one.nodes['porcentagens-filtros'];filter.elements.os.value='006260';one.handlers.search();assert(filter.elements.area_id.value==='','OS search clears sector');
  filter.elements.area_id.value='1';one.handlers.sector();assert(filter.elements.os.value===''&&one.handlers.filterRequested,'sector navigation clears OS search');
  let prevented=false;one.handlers.keydown({key:'Enter',preventDefault(){prevented=true;}});assert(prevented&&calls===0,'Enter in percentage field never saves implicitly');
  let warned=false;one.handlers.unload({preventDefault(){warned=true;}});assert(warned,'warn before leaving unsaved');
  const two=page(2,0);two.edit(50);assert(Object.keys(JSON.parse(stored.values().next().value).rows).length===2,'preserve drafts across pages');
  const restored=page(1,20);assert(restored.input.value==='35'&&restored.nodes['porcentagens-pendentes'].textContent.includes('2'),'restore page and total pending count');
  const submit=restored.handlers.submit({preventDefault(){}});restored.handlers.submit({preventDefault(){}});assert(calls===1,'double click one POST');
  const rows=JSON.parse(restored.nodes['porcentagens-json'].value);assert(rows.length===2&&rows[0].base===20&&rows[0].percentual==='35','one save includes other pages and accumulated baseline');
  resolveRequest({ok:true,redirected:false,json:async()=>({ok:true,quantidade:2})});await submit;assert(reloads===1&&Object.keys(JSON.parse(stored.values().next().value).rows).length===0,'clear only after persisted success');
  const retry=page(1,20);retry.edit(40);fail=true;await retry.handlers.submit({preventDefault(){}});assert(stored.values().next().value.includes('40')&&!retry.input.disabled,'failure preserves editable draft');
  retry.edit(20);assert(retry.nodes['porcentagens-salvar'].disabled,'restored original value is not a change');
  const invalid=page(2,0);invalid.edit(101);const other=page(1,20);const previous=calls;await other.handlers.submit({preventDefault(){}});assert(calls===previous,'validate off-page percentages before submit');
  const isolated=page(1,20,'user2-period1-area1');assert(isolated.input.value==='20','isolate drafts by user period and principal sector');
  return 'Porcentagens em lote: rascunho entre páginas, acumulados, alterações reais, CSRF, envio único, validação de outras páginas, falha e aviso de saída: OK';
}
