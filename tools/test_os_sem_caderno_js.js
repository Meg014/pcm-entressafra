async function testOsSemCaderno(source) {
  const assert = (value, message) => { if (!value) throw new Error(message); };
  let changes = [], submissions = 0, resolveSelection;
  const status = {}, counter = {};
  const boxes = [true, false].map(checked => ({checked, closest: () => ({lastElementChild:{}}), addEventListener(type, handler) {changes.push(handler);}}));
  const save = {}, all = {addEventListener(type, handler) {this.handler = handler;}};
  const selection = {action:'/os-sem-caderno?token=preview',elements:{_csrfToken:{value:'csrf'}},addEventListener(){},querySelectorAll: selector => selector === 'button' ? [save] : boxes,querySelector: () => save};
  const saveFetch = async (url, options) => {submissions++;assert(options.headers['X-CSRF-Token']==='csrf','selection CSRF');return new Promise(resolve=>{resolveSelection=resolve;});};
  Function('document','fetch','FormData',source)({addEventListener(){},querySelectorAll:()=>[counter],getElementById: id => ({'pendencias-selecao':selection,'pendencias-todas-pagina':all,'pendencias-selection-status':status}[id] || null)},saveFetch,class{set(){}});
  assert(all.indeterminate, 'restore partial page selection');
  all.checked = true; all.handler(); changes[0]();
  assert(boxes.every(box => box.checked) && submissions === 1, 'select page and serialize concurrent selection changes');
  resolveSelection({ok:true,redirected:false,json:async()=>({ok:true,selecionadas:2})});
  for(let i=0;i<10;i++)await Promise.resolve();
  assert(submissions===2,'save latest checkbox state after response');
  resolveSelection({ok:true,redirected:false,json:async()=>({ok:true,selecionadas:2})});
  for(let i=0;i<10;i++)await Promise.resolve();
  assert(counter.textContent===2&&status.textContent==='Seleção salva.','update selection count without reloading');
  for (const auto of ['0', '1']) {
    let submit, resolve, calls = 0, reloads = 0, failed = false;
    const button = {disabled: false}, error = {textContent: ''};
    const form = {action: '/os-sem-caderno?token=preview', elements: {_csrfToken: {value: 'csrf'}}, querySelector: () => button, addEventListener(type, handler) {submit = handler;}};
    const fetch = async (url, options) => {
      calls++; assert(options.headers['X-CSRF-Token'] === 'csrf' && options.method === 'POST', 'authenticated POST continuation');
      if (failed) throw new Error('offline');
      return new Promise(done => {resolve = done;});
    };
    Function('document', 'fetch', 'FormData', 'window', source)({getElementById: id => ({'pendencias-progress': {dataset: {auto}}, 'pendencias-continuar': form, 'pendencias-error': error}[id] || null)}, fetch, class {}, {location: {reload() {reloads++;}}});
    if (auto === '0') assert(calls === 0, 'paused processing waits for retry');
    submit({preventDefault() {}}); submit({preventDefault() {}});
    assert(calls === 1 && button.disabled, 'one request despite double click');
    resolve({ok: true, redirected: false, json: async () => ({ok: true})});
    for(let i = 0; i < 10; i++) await Promise.resolve();
    assert(reloads === 1, 'refresh progress after committed block');
  }
  let handler, attempts = 0;
  const error = {}, button = {};
  const form = {action: '/os-sem-caderno', elements: {_csrfToken: {value: 'csrf'}}, querySelector: () => button, addEventListener(type, fn) {handler = fn;}};
  Function('document','fetch','FormData','window',source)({getElementById: id => ({'pendencias-progress': {dataset: {auto:'0'}}, 'pendencias-continuar': form, 'pendencias-error': error}[id] || null)}, async () => {attempts++; throw new Error('offline');}, class {}, {location:{reload(){throw new Error('unexpected reload');}}});
  await handler({preventDefault(){}});await handler({preventDefault(){}});
  assert(attempts === 2 && !button.disabled && error.textContent.includes('permanecem salvas'), 'failure preserves selection and allows retry');
  return 'O.S. sem Caderno: seleção da página, continuação automática, CSRF, duplo clique, falha e retomada: OK';
}
