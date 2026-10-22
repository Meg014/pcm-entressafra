// Pure JavaScript behavioral harness. Run in a JS host with the two source strings.
// No browser, network, employee data or persistence is used here.
async function testAutocomplete(employeeSource, orderSource, withoutFilters=false) {
  class Element {
    constructor(tag = 'div') { this.tagName = tag; this.value = ''; this.children = []; this.listeners = {}; this.attributes = {}; this.dataset = {}; this.hidden = true; this.textContent = ''; }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    emit(name, values = {}) { const event = {key: '', preventDefault() {}, ...values}; for (const listener of this.listeners[name] || []) listener(event); }
    setAttribute(name, value) { this.attributes[name] = value; }
    removeAttribute(name) { delete this.attributes[name]; }
    replaceChildren() { this.children = []; }
    append(child) { this.children.push(child); }
    querySelectorAll() { return this.children.filter(child => child.tagName === 'button' && !child.disabled); }
    click() { if (!this.disabled) this.emit('click'); }
    focus() {} scrollIntoView() {} setCustomValidity(value) { this.validity = value; } reportValidity() {}
    contains() { return true; }
  }
  const assert = (value, message) => { if (!value) throw new Error(message); };
  async function scenario(source, employee) {
    const prefix = employee ? 'funcionario' : 'os';
    const elements = {};
    const get = id => elements[id] ||= new Element();
    const root = get(prefix + '-autocomplete'); root.dataset.lookup = '/apontamentos/' + (employee ? 'funcionarios' : 'ordens'); root.dataset.link = '/atividades/add';
    const input = get(prefix + '-pesquisa'), key = get(employee ? 'funcionario-chave' : 'atividade-selecionada');
    const list = get(prefix + '-resultados'), message = get(prefix + '-aviso');
    root.querySelector = () => input;
    const form = new Element('form'); input.form = form; form.elements = {};
    for (const name of ['entressafra_id', 'setor_id', 'caderno_id', 'inicio', 'fim', 'descricao']) form.elements[name] = new Element('input');
    form.elements.entressafra_id.value = '1'; form.elements.setor_id.value = '7'; form.elements.caderno_id.value = '2'; form.elements.inicio.value = '2026-10-21T07:00'; form.elements.fim.value = '2026-10-21T11:00'; form.elements.descricao.value = 'Trabalho';
    if(withoutFilters)for(const name of ['entressafra_id','setor_id','caderno_id'])delete form.elements[name];
    const originalValues = JSON.stringify(Object.values(form.elements).map(field=>field.value));
    const timers = new Map(); let timerId = 0, calls = 0, url, fail = false, rows;
    const normal = {chave:'000123', nome:'Pessoa de teste', matricula:'000123', funcao:'00001', situacao:'NORMAL', ativo:true};
    const blocked = {...normal, chave:'', ativo:false, situacao:'FÉRIAS'};
    const linked = {id:'9', selecionavel:true, numero:'005523', filial:'01', servico:'ENTRESSAFRA', codigo_area:'CALDEI', setor_id:'7', setor:'Setor', caderno:'Caderno', situacao:'L'};
    rows = employee ? [blocked, normal, {...blocked,bloqueio:'Matrícula ambígua'}] : [{...linked,id:null,selecionavel:false,aviso:'Solicite vínculo ao PCM'},linked];
    const document = {getElementById:get, createElement:tag=>new Element(tag), addEventListener() {}};
    let identified=false;
    const fetch = async (target,options) => { if(options?.method==='POST'){identified=JSON.parse(options.body).os_chave==='os-nova';return {ok:true,redirected:false,json:async()=>({order:linked})};}calls++; url = target; if (fail) throw new Error('Falha de conexão'); return {ok:true, redirected:false, json:async()=>({results:rows,has_more:false,message:'Sem resultado'})}; };
    new Function('document','window','fetch','setTimeout','clearTimeout','AbortController','URL',source)(document,{location:{origin:'http://localhost'}},fetch,fn=>{timers.set(++timerId,fn);return timerId;},id=>timers.delete(id),AbortController,URL);
    const flush = async () => { for (const [id, fn] of [...timers]) { timers.delete(id); await fn(); } };
    input.value = 'M'; input.emit('input'); await flush(); assert(calls === 0, prefix + ': minimum length');
    input.value = 'Ma'; input.emit('input'); input.value = 'Maria'; input.emit('input'); await flush();
    assert(calls === 1 && url.searchParams.get('q') === 'Maria', prefix + ': debounce and query');
    assert(!list.hidden && list.children[0].disabled, prefix + ': blocked result visible');
    if (!employee) assert((withoutFilters?!url.searchParams.get('setor_id'):url.searchParams.get('setor_id') === '7') && list.children.some(el=>el.tagName === 'a'), 'OS: scope and PCM action');
    input.emit('keydown',{key:'ArrowDown'}); input.emit('keydown',{key:'Enter'});
    assert(key.value === (employee ? '000123' : '9'), prefix + ': keyboard selection');
    if (employee) {
      assert(!get('funcionario-resumo').hidden && get('funcionario-resumo-nome').textContent === normal.nome && get('funcionario-resumo-matricula').textContent === 'Matrícula 000123', 'employee: compact confirmed summary');
      assert(!elements['funcionario-nome'] && !elements['funcionario-matricula'] && !elements['funcionario-funcao'] && !elements['funcionario-situacao'], 'employee: removed fields are never accessed');
    }
    assert(JSON.stringify(Object.values(form.elements).map(field=>field.value)) === originalValues, prefix + ': other form values preserved');
    get(prefix + '-limpar').click(); assert(key.value === '' && input.value === '', prefix + ': clear');
    if (employee) { assert(get('funcionario-resumo').hidden && get('funcionario-resumo-nome').textContent === '', 'employee: clear summary'); }
    assert(JSON.stringify(Object.values(form.elements).map(field=>field.value)) === originalValues, prefix + ': clear preserves remaining values');
    input.value = 'Maria'; input.emit('input'); input.emit('keydown',{key:'Escape'}); await flush();
    assert(list.hidden && calls === 1, prefix + ': escape cancels pending lookup');
    if(!employee&&withoutFilters){root.dataset.identify='/operacao/identificar';rows=[{...linked,id:null,selecionavel:true,os_chave:'os-nova'}];input.value='nova';input.emit('input');await flush();list.children[0].click();for(let i=0;i<8;i++)await Promise.resolve();assert(identified&&key.value==='9','OS: server identity confirmation before selecting an order without a notebook');}
    rows = []; input.emit('input'); await flush(); assert(list.hidden && message.textContent, prefix + ': empty');
    fail = true; input.emit('input'); await flush(); assert(message.textContent === 'Falha de conexão', prefix + ': connection failure');
    return prefix + ': debounce, mouse/keyboard controls, blocked rows, selection, clear, empty/error states and preservation: OK';
  }
  return [await scenario(employeeSource,true),await scenario(orderSource,false)];
}
