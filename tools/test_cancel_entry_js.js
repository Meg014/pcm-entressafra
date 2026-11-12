const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../webroot/js/cancel-entry.js'), 'utf8');

function harness(responses) {
  const handlers = {};
  const state = {calls: [], messages: [], closed: false};
  const error = {textContent: ''};
  const submit = {disabled: false};
  const reason = {value: 'Motivo informado', setCustomValidity() {}};
  const form = {
    id: 'cancel-entry-form', action: 'http://test/fila-pcm/os/10/excluir-apontamento',
    reportValidity: () => true,
    querySelector: selector => ({'[name="motivo"]': reason, '[type="submit"]': submit, '[name="apontamento_id"]': {value: '7'}}[selector])
  };
  const main = {querySelector: () => ({}), prepend: node => state.messages.push(node.textContent)};
  const document = {
    addEventListener: (name, handler) => {handlers[name] = handler;},
    getElementById: id => id === 'cancel-entry-error' ? error : {close() {state.closed = true;}},
    querySelector: () => ({replaceWith() {state.replaced = true;}}),
    createElement: () => ({setAttribute() {}})
  };
  vm.runInNewContext(source, {
    document, TypeError, location: {href: 'http://test/fila-pcm/os/10'},
    FormData: class {constructor(form) {this.form = form;}},
    DOMParser: class {parseFromString() {return {querySelector: () => main};}},
    fetch: async (url, options) => {
      state.calls.push({url, options});
      const result = responses.shift();
      if (!result) throw new Error('Unexpected request');
      return typeof result === 'function' ? result() : result;
    }
  });
  return {state, error, submit, run: () => handlers.submit({target: form, preventDefault() {}})};
}
function response(status, type, body) {
  return {status, ok: status >= 200 && status < 300, headers: {get: () => type},
    json: async () => body, text: async () => '<main id="conteudo"></main>'};
}
(async () => {
  let checks = 0;
  for (const status of [200, 401, 403, 404, 500]) {
    const html = response(status, 'text/html', null);
    html.json = () => {throw new Error('HTML must never be parsed as JSON');};
    const h = harness([html]);await h.run();
    assert.ok(h.error.textContent);assert.doesNotMatch(h.error.textContent, /Unexpected token|HTML must never/);
    assert.equal(h.state.messages.length, 0);assert.equal(h.state.calls.length, 1);checks++;
  }
  for (const status of [401, 403, 404, 409, 422, 500]) {
    const h = harness([response(status, 'application/json', {success: false, error: 'Mensagem compreensível'})]);await h.run();
    assert.equal(h.error.textContent, 'Mensagem compreensível');assert.equal(h.state.messages.length, 0);checks++;
  }
  const malformed = response(200, 'application/json', null);
  malformed.json = async () => {throw new SyntaxError('Unexpected token <');};
  const invalid = harness([malformed]);await invalid.run();
  assert.doesNotMatch(invalid.error.textContent, /Unexpected token/);assert.equal(invalid.state.messages.length, 0);checks++;
  for (const body of [{success: true, cancelado: false, apontamento_id: 7}, {success: true, cancelado: true, apontamento_id: 8}]) {
    const h = harness([response(200, 'application/json', body)]);await h.run();
    assert.equal(h.state.messages.length, 0);assert.equal(h.state.calls.length, 1);checks++;
  }
  let release;
  const pending = new Promise(resolve => {release = resolve;});
  const h = harness([() => pending, response(200, 'text/html', null)]);
  const first = h.run();await h.run();assert.equal(h.state.calls.length, 1);
  assert.equal(h.state.messages.length, 0);assert.equal(h.submit.disabled, true);
  release(response(200, 'application/json', {success: true, cancelado: true, apontamento_id: 7}));await first;
  assert.equal(h.state.calls.length, 2);assert.equal(h.state.calls[0].options.method, 'POST');
  assert.equal(h.state.calls[0].options.headers.Accept, 'application/json');
  assert.equal(h.state.calls[0].options.redirect, 'manual');
  assert.equal(h.state.messages.length, 1);assert.ok(h.state.closed && h.state.replaced);checks++;
  const refresh = harness([response(200, 'application/json', {success: true, cancelado: true, apontamento_id: 7}), response(500, 'text/html', null)]);
  await refresh.run();assert.match(refresh.error.textContent, /Apontamento cancelado/);assert.equal(refresh.submit.disabled, true);checks++;
  console.log(`OK: ${checks} cenários JavaScript (HTML inesperado, JSON inválido, erros HTTP, persistência e submissão duplicada).`);
})().catch(error => {console.error(error);process.exitCode = 1;});
