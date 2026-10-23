(() => {
  const root = document.getElementById('porcentagens-lote');
  if (!root) return;
  const filters = document.getElementById('porcentagens-filtros');
  filters.elements.area_id.addEventListener('change', () => filters.requestSubmit());
  const form = document.getElementById('porcentagens-form');
  if (!form) return;
  const storageKey = root.dataset.storage;
  const button = document.getElementById('porcentagens-salvar');
  const error = document.getElementById('porcentagens-erro');
  const counter = document.getElementById('porcentagens-pendentes');
  const inputs = Array.from(root.querySelectorAll('[data-percentage]'));
  let draft = {key:form.dataset.key, rows:{}}, busy = false, internal = false, storageOK = true;
  try { const saved = sessionStorage.getItem(storageKey); if (saved) draft = JSON.parse(saved); }
  catch (_) { storageOK = false; error.textContent = 'Não foi possível recuperar o rascunho. Mantenha a página aberta até salvar.'; }
  if (!draft || typeof draft.rows !== 'object' || !draft.key) {error.textContent = 'Rascunho inválido. Solicite conferência antes de continuar.';return;}
  const remember = () => {
    try {sessionStorage.setItem(storageKey, JSON.stringify(draft));storageOK = true;}
    catch (_) {storageOK = false;error.textContent = 'Não foi possível guardar o rascunho entre páginas. Salve antes de navegar.';}
    const count = Object.keys(draft.rows).length;
    counter.textContent = count ? `${count} O.S. alterada(s), incluindo outras páginas.` : 'Nenhuma alteração pendente.';
    button.disabled = busy || !count;
  };
  for (const row of JSON.parse(form.dataset.pending || '[]')) draft.rows[row.atividade_id] = row;
  inputs.forEach(input => {
    const id = input.dataset.id;
    if (draft.rows[id]) input.value = draft.rows[id].percentual;
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        const next = inputs[inputs.indexOf(input) + 1];
        if (next) {next.focus();next.select();}
      }
    });
    input.addEventListener('input', () => {
      const value = input.value.trim();
      if (value === '' || Number(value) === Number(input.dataset.base)) delete draft.rows[id];
      else draft.rows[id] = {atividade_id:Number(id), percentual:value, base:Number(input.dataset.base), revisao:Number(input.dataset.revision), referencia:root.dataset.reference};
      draft.key = crypto.randomUUID();remember();
    });
  });
  remember();
  root.querySelectorAll('[data-batch-page]').forEach(link => link.addEventListener('click', event => {
    if (busy || !storageOK) {event.preventDefault();error.textContent = 'Salve as alterações antes de navegar.';return;}
    internal = true;
  }));
  filters.addEventListener('submit', event => {
    if (busy || !storageOK) {event.preventDefault();error.textContent = 'Salve as alterações antes de navegar.';return;}
    internal = filters.elements.area_id.value === new URL(location.href).searchParams.get('area_id');
  });
  window.addEventListener('beforeunload', event => {
    if (Object.keys(draft.rows).length && !internal) {event.preventDefault();event.returnValue = '';}
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();if (busy) return;
    const rows = Object.values(draft.rows);
    if (!rows.length) return;
    if (rows.some(row => !/^\d+(?:[.,]\d{1,2})?$/.test(String(row.percentual)) || Number(String(row.percentual).replace(',','.')) > 100)) {error.textContent = 'Confira os percentuais de todas as páginas: use valores entre 0 e 100, com até duas casas decimais.';return;}
    busy = true;remember();error.textContent = '';
    inputs.forEach(input => {input.disabled = true;});
    document.getElementById('porcentagens-chave').value = draft.key;
    document.getElementById('porcentagens-json').value = JSON.stringify(rows);
    try {
      const response = await fetch(form.action, {method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':form.elements._csrfToken.value}});
      if (response.redirected) throw new Error('Entre novamente para retomar o lançamento.');
      if (response.status === 403) throw new Error('Sem autorização para salvar este setor ou sessão expirada.');
      const result = await response.json();
      if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar.');
      sessionStorage.removeItem(storageKey);draft.rows = {};internal = true;location.reload();
    } catch (failure) {error.textContent = failure.message + ' As alterações pendentes foram mantidas.';}
    finally {busy = false;inputs.forEach(input => {input.disabled = false;});remember();}
  });
})();
