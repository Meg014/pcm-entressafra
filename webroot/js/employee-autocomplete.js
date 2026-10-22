(() => {
  const root = document.getElementById('funcionario-autocomplete');
  if (!root) return;
  const input = root.querySelector('[role="combobox"]');
  const key = document.getElementById('funcionario-chave');
  const list = document.getElementById('funcionario-resultados');
  const message = document.getElementById('funcionario-aviso');
  const summary = document.getElementById('funcionario-resumo');
  const selectedName = document.getElementById('funcionario-resumo-nome');
  const selectedNumber = document.getElementById('funcionario-resumo-matricula');
  let timer, controller, version = 0, active = -1;
  const close = () => {
    list.hidden = true; input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant'); active = -1;
  };
  const reset = () => {
    key.value = ''; selectedName.textContent = ''; selectedNumber.textContent = ''; summary.hidden = true;
  };
  const choose = person => {
    if (person.ativo !== true) return;
    clearTimeout(timer); if (controller) controller.abort(); ++version;
    key.value = person.chave;
    input.value = person.nome;
    selectedName.textContent = person.nome; selectedNumber.textContent = `Matrícula ${person.matricula}`; summary.hidden = false;
    input.setCustomValidity(''); message.textContent = 'Funcionário selecionado. O servidor confirmará a matrícula e a situação ao salvar.';
    close(); input.focus();
  };
  input.addEventListener('input', () => {
    clearTimeout(timer); if (controller) controller.abort();
    const current = ++version; reset(); close(); list.replaceChildren(); input.setCustomValidity('');
    const term = input.value.trim();
    if (term.length < 2 || term.length > 60) { message.textContent = 'Digite de 2 a 60 caracteres.'; return; }
    message.textContent = 'Pesquisando…';
    timer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const url = new URL(root.dataset.lookup, window.location.origin); url.searchParams.set('q', term);
        const response = await fetch(url, {signal:controller.signal, headers:{Accept:'application/json'}, cache:'no-store'});
        if (current !== version) return;
        if (response.redirected) throw new Error('Entre novamente no sistema para continuar.');
        const data = await response.json(); if (current !== version) return;
        if (!response.ok) throw new Error(data.error || 'Consulta indisponível. Tente novamente.');
        list.replaceChildren();
        data.results.forEach((person,index) => {
          const option = document.createElement('button'); option.type = 'button';
          option.id = `${list.id}-${index}`; option.setAttribute('role','option'); option.setAttribute('aria-selected','false');
          option.className = 'd-block w-100 text-start border-0 border-bottom p-3 bg-white';
          option.textContent = `${person.nome} · Matrícula ${person.matricula} · ${person.situacao}${person.bloqueio ? ' · ' + person.bloqueio : ''}`;
          option.disabled = person.ativo !== true; option.setAttribute('aria-disabled',String(option.disabled));
          option.addEventListener('mousedown',event => event.preventDefault()); option.addEventListener('click',() => choose(person)); list.append(option);
        });
        list.hidden = data.results.length === 0; input.setAttribute('aria-expanded',String(!list.hidden));
        message.textContent = data.warning || (data.has_more ? 'Mostrando os primeiros 20 resultados. Refine o nome ou a matrícula.' : (data.results.length ? 'Selecione um funcionário NORMAL. Férias e outras situações estão bloqueadas.' : 'Nenhum funcionário encontrado.'));
      } catch (error) {
        if (error.name !== 'AbortError' && current === version) { close(); message.textContent = error.message; }
      }
    },300);
  });
  input.addEventListener('keydown',event => {
    if (event.key === 'Escape') { clearTimeout(timer); if (controller) controller.abort(); ++version; close(); return; }
    const options = [...list.querySelectorAll('button:not(:disabled)')];
    if (list.hidden || !options.length) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault(); active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
      options.forEach((option,index) => option.setAttribute('aria-selected',String(index === active)));
      input.setAttribute('aria-activedescendant',options[active].id); options[active].scrollIntoView({block:'nearest'});
    } else if (event.key === 'Enter') { event.preventDefault(); if (active >= 0) options[active].click(); }
  });
  document.getElementById('funcionario-limpar').addEventListener('click', () => {
    clearTimeout(timer); if (controller) controller.abort(); ++version;
    reset(); close(); list.replaceChildren(); input.value = ''; input.setCustomValidity(''); message.textContent = 'Digite pelo menos 2 caracteres para pesquisar.'; input.focus();
  });
  document.addEventListener('click',event => { if (!root.contains(event.target)) close(); });
  input.form.addEventListener('submit',event => {
    if (!key.value) { event.preventDefault(); input.setCustomValidity('Selecione um funcionário válido da lista.'); input.reportValidity(); }
  });
})();
