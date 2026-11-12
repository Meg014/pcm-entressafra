(() => {
  let opener;
  document.addEventListener('click', event => {
    const dialog = document.getElementById('cancel-entry-dialog');
    if (!dialog) return;
    if (event.target.closest('[data-cancel-close]')) { dialog.close(); opener?.focus(); return; }
    const button = event.target.closest('[data-cancel-entry]');
    if (!button) return;
    opener = button;
    const form = dialog.querySelector('form');
    form.reset();
    dialog.querySelector('#cancel-entry-error').textContent = '';
    dialog.querySelector('#cancel-entry-id').value = button.dataset.cancelEntry;
    dialog.querySelector('#cancel-entry-revision').value = button.dataset.revision;
    const cells = button.closest('tr').cells;
    const details = dialog.querySelector('#cancel-entry-details');
    details.replaceChildren();
    ['Funcionário e matrícula', 'Data', 'Horários', 'Horas', 'Situação'].forEach((label, i) => {
      const line = document.createElement('p');
      line.textContent = label + ': ' + cells[[1,2,3,4,6][i]].textContent.trim();
      details.append(line);
    });
    const totvs = button.dataset.totvs === '1';
    dialog.querySelector('#cancel-entry-warning').hidden = !totvs;
    dialog.querySelector('#cancel-entry-ack').hidden = !totvs;
    const ack = form.querySelector('[name="ciencia_totvs"]');
    ack.required = totvs; ack.disabled = !totvs;
    dialog.showModal();
    dialog.querySelector('#cancel-entry-reason').focus();
  });
  document.addEventListener('submit', async event => {
    if (event.target.id !== 'cancel-entry-form') return;
    event.preventDefault();
    const form = event.target;
    const reason = form.querySelector('[name="motivo"]');
    reason.setCustomValidity(reason.value.trim() ? '' : 'Informe o motivo da exclusão.');
    if (!form.reportValidity()) { reason.oninput = () => reason.setCustomValidity(''); return; }
    const submit = form.querySelector('[type="submit"]');
    submit.disabled = true;
    const error = document.getElementById('cancel-entry-error');
    let saved = false;
    try {
      const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{'X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin'});
      const result = await response.json();
      if (!response.ok || !result.success) throw new Error(result.error || 'Não foi possível excluir o apontamento.');
      saved = true;
      const page = await fetch(location.href, {credentials:'same-origin', cache:'no-store'});
      if (!page.ok) throw new Error('Falha ao atualizar a lista.');
      const html = new DOMParser().parseFromString(await page.text(), 'text/html');
      const main = html.querySelector('main');
      if (!main) throw new Error('Falha ao atualizar a lista.');
      document.getElementById('cancel-entry-dialog').close();
      document.querySelector('main').replaceWith(main);
      const message = document.createElement('div');
      message.className = 'alert alert-success'; message.setAttribute('role','status');
      message.textContent = 'Apontamento cancelado com sucesso. Histórico preservado.';
      main.prepend(message);
    } catch (e) {
      error.textContent = saved ? 'Apontamento cancelado. Atualize a página para consultar a lista.' : e.message;
    } finally { if (!saved) submit.disabled = false; }
  });
})();
