async function testPorcentagensLote(source) {
 const assert=(value,message)=>{if(!value)throw Error(message);};
 const stored=new Map();let serial=0,calls=0,reloads=0,reply,transportError=false,removeError=false;
 const storage={getItem:k=>stored.get(k)||null,setItem:(k,v)=>stored.set(k,v),removeItem:k=>{if(removeError)throw Error('storage');stored.delete(k);}};
 function page(id,base=null,revision=0,key='u1-p1-a1'){
  const handlers={},details={dataset:{},hidden:true},reason={value:'',addEventListener:(t,f)=>handlers.reason=f},confirm={checked:false,addEventListener:(t,f)=>handlers.confirm=f},useSaved={addEventListener:(t,f)=>handlers.useSaved=f};
  const cell={classList:{toggle(){}},querySelector:s=>s==='details'?details:s==='[data-correction-reason]'?reason:s==='[data-confirm-reduction]'?confirm:useSaved};
  const input={value:base===null?'':String(base),dataset:{id:String(id),base:base===null?'':String(base),reference:'2026-10-26',record:String(revision),revision:String(revision),os:String(id),week:'2'},closest:()=>cell,addEventListener:(t,f)=>handlers[t]=f,removeAttribute(){},setAttribute(){}};
  const filters={elements:{area_id:{value:'1',addEventListener:(t,f)=>handlers.sector=f},os:{value:'',addEventListener:(t,f)=>handlers.search=f}},requestSubmit(){handlers.filter({preventDefault(){}});},addEventListener:(t,f)=>handlers.filter=f};
  const form={dataset:{key:'12345678901234567890123456789012',pending:'[]'},elements:{_csrfToken:{value:'csrf'}},action:'/lancar-porcentagem?area_id=1',querySelectorAll:()=>[input,reason,confirm],addEventListener:(t,f)=>handlers.submit=f};
  const root={dataset:{storage:key},querySelectorAll:s=>s==='[data-percentage]'?[input]:[{addEventListener:(t,f)=>handlers.page=f}]};
  const nodes={'porcentagens-lote':root,'porcentagens-filtros':filters,'porcentagens-form':form,'porcentagens-salvar':{},'porcentagens-erro':{},'porcentagens-pendentes':{},'porcentagens-chave':{},'porcentagens-json':{}};
  class Payload {constructor(){this.json=nodes['porcentagens-json'].value;}get(k){return k==='alteracoes_json'?this.json:undefined;}}
  const fetch=async(url,options)=>{calls++;assert(options.headers['X-CSRF-Token']==='csrf','CSRF');assert(JSON.parse(options.body.get('alteracoes_json')).length>0,'nonempty payload');if(transportError)throw Error('offline');return new Promise(resolve=>reply=resolve);};
  Function('document','sessionStorage','window','location','crypto','fetch','FormData',source)({getElementById:k=>nodes[k]},storage,{addEventListener:(t,f)=>handlers.unload=f},{reload(){reloads++;}},{randomUUID:()=>`12345678-1234-1234-1234-${String(++serial).padStart(12,'0')}`},fetch,Payload);
  return {input,handlers,nodes,details,edit(v){input.value=String(v);handlers.input();},submit(){return handlers.submit({preventDefault(){}});}};
 }
 const one=page(1);one.edit(0);
 const two=page(2);two.edit(50);
 const restored=page(1);assert(restored.input.value==='0','zero draft survives navigation');
 const save=restored.submit();restored.submit();assert(calls===1,'double click sends one POST');
 const rows=JSON.parse(restored.nodes['porcentagens-json'].value);assert(rows.length===2&&rows.some(r=>r.percentual==='0'&&r.base===null),'all changed cells, including zero and other pages, sent');
 reply({ok:true,json:async()=>({ok:true,quantidade:2,celulas:rows})});await save;assert(reloads===1,'confirmed save reloads database values');
 const initial=page(1,10,1);initial.edit(20);
 const stale=page(1,30,2);assert(stale.input.value==='20'&&stale.details.open,'restore draft and show changed database baseline');stale.edit(40);
 const conflict=stale.submit();const staleRows=JSON.parse(stale.nodes['porcentagens-json'].value);
 assert(staleRows[0].base===10&&staleRows[0].base_registro===1&&staleRows[0].revisao===1,'re-edit after reload retains original baseline');
 reply({ok:false,status:422,json:async()=>({error:'outra pessoa alterou esta célula'})});await conflict;
 assert(stale.input.value==='40'&&!stale.input.disabled&&stored.get('u1-p1-a1').includes('40'),'conflict preserves editable draft');
 stale.handlers.useSaved();assert(stale.input.value==='30'&&stale.nodes['porcentagens-salvar'].disabled,'explicit discard adopts loaded database value');
 stale.edit(45);const corrected=stale.submit();assert(JSON.parse(stale.nodes['porcentagens-json'].value)[0].revisao===2,'new edit uses explicitly accepted revision');
 reply({ok:true,json:async()=>({ok:true,quantidade:1,celulas:[{atividade_id:1,referencia:'2026-10-26'}]})});await corrected;
 const offline=page(3);offline.edit(25);transportError=true;await offline.submit();transportError=false;
 assert(offline.input.value==='25'&&stored.get('u1-p1-a1').includes('25'),'network error preserves draft');
 const failure=offline.submit();reply({ok:false,status:503,json:async()=>({ok:false,error:'Falha no banco'})});await failure;
 assert(offline.nodes['porcentagens-erro'].textContent.includes('Falha no banco')&&reloads===2,'database failure never reloads as success');
 const malformed=offline.submit();reply({ok:true,json:async()=>({ok:true})});await malformed;assert(reloads===2,'incomplete acknowledgement does not clear draft');
 offline.edit(101);const n=calls;await offline.submit();assert(calls===n,'invalid value never sent');
 offline.edit(25);let blocked=false;offline.handlers.filter({preventDefault(){blocked=true;}});assert(!blocked&&stored.get('u1-p1-a1').includes('25'),'filter navigation preserves draft');
 const filterReturn=page(3);assert(filterReturn.input.value==='25','returning to filter restores draft');
 assert(page(3,null,0,'u2-p1-a1').input.value==='','drafts isolated by user');
 const sector=page(4,null,0,'u1-p1-a2');sector.edit(60);
 assert(page(3).input.value==='25','switching sectors and back retains the original draft');
 const final=filterReturn.submit();removeError=true;reply({ok:true,json:async()=>({ok:true,quantidade:1,celulas:[{atividade_id:3,referencia:'2026-10-26'}]})});await final;assert(reloads===3,'local storage cleanup failure does not misreport committed save');
 return 'OK: all cells, zero, pagination, filters, reload, stale revisions, conflicts, retry, database/network errors and acknowledgements';
}
if(typeof module!=='undefined'&&require.main===module){
 const fs=require('fs'),path=require('path');
 testPorcentagensLote(fs.readFileSync(path.join(__dirname,'../webroot/js/porcentagens-lote.js'),'utf8')).then(console.log).catch(error=>{console.error(error);process.exitCode=1;});
}
