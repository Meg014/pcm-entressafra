(() => {
  const selection = document.getElementById('pendencias-selecao');
  if (selection) {
    const boxes = Array.from(selection.querySelectorAll('input[name="numeros[]"]:not(:disabled)'));
    const save = selection.querySelector('button[value="pagina"]');
    const all = document.getElementById('pendencias-todas-pagina');
    if (all && save) {
      all.checked = boxes.length > 0 && boxes.every(box => box.checked);
      all.indeterminate = boxes.some(box => box.checked) && !all.checked;
      let saving = false, dirty = false;
      const status = document.getElementById('pendencias-selection-status');
      const persist = async () => {
        document.getElementById('pendencias-resumo')?.setAttribute('hidden', '');
        dirty = true;
        if (saving) return;
        saving = true;
        const buttons = Array.from(selection.querySelectorAll('button'));
        buttons.forEach(button => {button.disabled = true;});
        try {
          while (dirty) {
            dirty = false;
            status.textContent = 'Salvando seleção…';
            const body = new FormData(selection);
            body.set('acao', 'pagina');
            const response = await fetch(selection.action, {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':selection.elements._csrfToken.value}});
            if (response.redirected) throw new Error('Entre novamente para retomar a seleção.');
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível salvar a seleção.');
            document.querySelectorAll('[data-selected-count]').forEach(counter => {counter.textContent = result.selecionadas;});
          }
          status.textContent = 'Seleção salva.';
          boxes.forEach(box => {box.closest('tr').lastElementChild.textContent = box.checked ? 'Selecionada' : '—';});
        } catch (failure) {
          dirty = true;
          status.textContent = failure.message + ' As marcações foram mantidas nesta página. Use Salvar seleção da página para tentar novamente.';
        } finally {
          saving = false;
          buttons.forEach(button => {button.disabled = false;});
          all.checked = boxes.length > 0 && boxes.every(box => box.checked);
          all.indeterminate = boxes.some(box => box.checked) && !all.checked;
        }
      };
      boxes.forEach(box => box.addEventListener('change', persist));
      all.addEventListener('change', () => { boxes.forEach(box => { box.checked = all.checked; }); persist(); });
      selection.addEventListener('submit', event => {
        if (event.submitter === save) {event.preventDefault();persist();}
      });
      document.addEventListener('click', event => {
        if ((saving || dirty) && event.target.closest('a')) {event.preventDefault();status.textContent = 'Aguarde a seleção ser salva antes de navegar.';}
      });
      document.addEventListener('submit', event => {
        if (event.target !== selection && (saving || dirty)) {event.preventDefault();status.textContent = 'Salve a seleção antes de continuar.';}
      });
    }
  }
  const root = document.getElementById('pendencias-progress');
  if (!root) return;
  const form = document.getElementById('pendencias-continuar');
  const error = document.getElementById('pendencias-error');
  let busy = false;
  const next = async event => {
    event?.preventDefault();
    if (busy) return;
    busy = true;
    const button = form.querySelector('button');
    button.disabled = true;
    error.textContent = '';
    try {
      const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': form.elements._csrfToken.value}});
      if (response.redirected) throw new Error('Entre novamente e selecione a entressafra para retomar.');
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Não foi possível concluir o bloco.');
      window.location.reload();
    } catch (failure) {
      error.textContent = failure.message + ' A consulta e a seleção permanecem salvas. Clique em Continuar processamento para retomar.';
      busy = false;
      button.disabled = false;
    }
  };
  form.addEventListener('submit', next);
  if (root.dataset.auto === '1') next();
})();
