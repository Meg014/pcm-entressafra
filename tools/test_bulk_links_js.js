// Pure JS DOM simulation; invoke testBulkLinks(source) in a JavaScript host.
async function testBulkLinks(source) {
  class Element {
    constructor(tag='div') { this.tagName=tag; this.value=''; this.children=[]; this.listeners={}; this.dataset={}; this.hidden=false; this.disabled=false; this.textContent=''; }
    addEventListener(name,fn) { (this.listeners[name] ||= []).push(fn); }
    emit(name,event={}) { for(const fn of this.listeners[name]||[]) fn({preventDefault(){},...event}); }
    click() { if (!this.disabled) this.emit('click'); }
    replaceChildren() { this.children=[]; }
    append(child) { this.children.push(child); }
    setAttribute() {}
    get options() { return this.children; }
    get selectedOptions() { return this.children.filter(option=>option.selected); }
    reportValidity() { return true; }
  }
  const assert=(value,message)=>{if(!value)throw new Error(message);}, nodes={};
  const get=id=>nodes[id] ||= new Element();
  const root=get('bulk-links'); root.dataset={api:'/vinculos-lote/api',token:'saved-preview'};
  const form=get('bulk-form'); form.elements={};
  for(const name of ['entressafra_id','setor_id','caderno_id','cc','bem','busca','_csrfToken']) form.elements[name]=new Element('input');
  form.elements._csrfToken.value='test-csrf';
  form.querySelectorAll=()=>Object.entries(form.elements).filter(([key])=>key!=='_csrfToken').map(([,value])=>value);
  const notebook=new Element('option'); notebook.value='3'; notebook.dataset={setores:'2,4',entressafra:'1'}; form.elements.caderno_id.append(notebook);
  const fixture={token:'saved-preview',estado:'pronto',destino:'Caderno teste',entressafra_nome:'2026/2027',setor_nome:'Mecânica',filtros:{entressafra_id:1,setor_id:4,caderno_id:3,area:'MECANI',filial:'01',servicos:['2425ME','ESMECA'],cc:'410',bem:'',busca:''},contagens:{encontradas:121,selecionadas:0,elegiveis:120,ja_vinculadas:1,bloqueadas:0,vinculadas:0,falhas:0},pagina:1,paginas:3,filtradas:121,elegiveis_filtradas:120,rows:[{id:'1',estado:'elegivel',selecionado:false,os:{numero:'000001',filial:'01',codigo_servico:'2425ME',servico:'ENTRESSAFRA',codigo_area:'MECANI',data_origem:'20260101',situacao:'L',termino:'N'}},{id:'2',estado:'vinculada',selecionado:false,caderno_atual:'Outro caderno',motivo:'Já vinculada',os:{numero:'000002',filial:'01',codigo_servico:'ESMECA',servico:'ENTRESSAFRA',codigo_area:'MECANI',data_origem:'20260101',situacao:'L',termino:'N'}}]};
  const calls=[],prompts=[]; let approve=false;
  const fetch=async(url,options)=>{
    const params=options.method==='POST'?JSON.parse(options.body):Object.fromEntries(url.searchParams);
    calls.push(params);
    if(params.acao==='selecionar') {
      assert(options.headers['X-CSRF-Token']==='test-csrf','POST must preserve CSRF');
      assert(params.todas===true&&params.quantidade_confirmada===120,'select all must confirm total across pages');
      fixture.contagens.selecionadas=120; fixture.rows[0].selecionado=true;
    }
    if(params.acao==='confirmar') { assert(params.quantidade_confirmada===120,'link confirmation count'); fixture.estado='processando'; }
    if(params.acao==='processar') { fixture.estado='concluido'; fixture.contagens.vinculadas=120; fixture.contagens.elegiveis=0; fixture.rows[0].estado='sucesso'; }
    return {ok:true,redirected:false,json:async()=>JSON.parse(JSON.stringify(fixture))};
  };
  const document={getElementById:get,createElement:tag=>new Element(tag)};
  class TestURL { constructor(path,origin='') { this.href=origin+path; this.searchParams=new Map(); } }
  new Function('document','fetch','URL','location','history','confirm','setTimeout','clearTimeout',source)(document,fetch,TestURL,{origin:'http://localhost',href:'http://localhost/vinculos-lote',pathname:'/vinculos-lote'},{replaceState(){}},text=>{prompts.push(text);return approve;},()=>1,()=>{});
  const settle=async()=>{for(let i=0;i<120;i++) await Promise.resolve();};
  await settle();
  assert(form.elements.entressafra_id.value==='1'&&form.elements.caderno_id.value==='3','restore destination on reload');
  assert(get('bulk-services').children.length===2,'restore multiple service codes');
  assert(form.elements.setor_id.value==='4' && !notebook.hidden,'same notebook available for another associated sector');
  assert(get('bulk-rows').children[1].children[0].children[0].disabled,'already linked row is blocked');
  assert(!calls.some(call=>call.acao==='confirmar'||call.acao==='processar'),'preview must not write links');
  const before=calls.length; get('bulk-all').click(); await settle(); assert(calls.length===before,'cancel selecting all');
  approve=true; get('bulk-all').click(); await settle();
  assert(calls.some(call=>call.acao==='selecionar'),'select all request');
  approve=false; const beforeConfirm=calls.length; get('bulk-confirm').click(); await settle(); assert(calls.length===beforeConfirm,'cancel linking');
  approve=true; get('bulk-confirm').click(); await settle();
  assert(calls.filter(call=>call.acao==='confirmar').length===1&&calls.filter(call=>call.acao==='processar').length===1,'link only after explicit confirmation');
  assert(get('bulk-title').textContent==='Resultado da vinculação','final result');
  assert(prompts.some(text=>text.includes('2026/2027')&&text.includes('Mecânica')&&text.includes('Caderno teste')),'confirmation must identify destination');
  return 'UI lote: restauração, serviços múltiplos, bloqueios, seleção entre páginas, cancelamento/confirmação, CSRF e resultado: OK';
}
