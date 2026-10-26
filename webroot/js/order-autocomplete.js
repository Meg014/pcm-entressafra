(() => {
  const root = document.getElementById('os-autocomplete');
  if (!root) return;
  const input = document.getElementById('os-pesquisa'), key = document.getElementById('atividade-selecionada');
  const list = document.getElementById('os-resultados'), message = document.getElementById('os-aviso'), summary = document.getElementById('os-resumo');
  const filters = ['entressafra_id', 'setor_id', 'caderno_id'];
  let timer, controller, version = 0, active = -1;
  const identification = order => `O.S. ${order.numero}\nDescrição: ${order.descricao || 'Descrição não informada'}\n${order.rotulo_setor || 'Setor'}: ${order.setor_apresentacao || 'Não definido'}`;
  const close = () => {
    list.hidden = true; input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1;
  };
  const cancel = () => { clearTimeout(timer); if (controller) controller.abort(); ++version; close(); };
  const clear = () => {
    cancel(); key.value = ''; input.value = ''; summary.textContent = ''; summary.hidden = true; list.replaceChildren();
    message.dataset.lookupError='0';
    input.setCustomValidity(''); message.textContent = 'Digite pelo menos 2 caracteres para pesquisar.';
    if(root.dataset.completion==='1')root.dispatchEvent(new CustomEvent('pcm-entry-order',{detail:null}));
    if(root.dataset.progress==='1')window.dispatchEvent(new CustomEvent('pcm-order-selected',{detail:null}));
  };
  const choose = async order => {
    if (order.selecionavel !== true) return;
    if((!order.id||order.confirmar_origem)&&root.dataset.identify){
      cancel();const current=version;key.value='';summary.hidden=true;if(root.dataset.progress==='1')window.dispatchEvent(new CustomEvent('pcm-order-selected',{detail:null}));message.textContent='Confirmando O.S. no TOTVS…';
      try{const response=await fetch(root.dataset.identify,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':input.form.elements._csrfToken?.value||''},body:JSON.stringify({os_chave:order.os_chave,tipo:root.dataset.progress==='1'?'evolucao':'apontamento'})});if(current!==version)return;if(response.redirected)throw new Error('Entre novamente no sistema para continuar.');const data=await response.json();if(!response.ok)throw new Error(data.error||'Não foi possível confirmar a O.S.');return choose(data.order);}catch(error){if(current===version){message.dataset.lookupError='1';message.textContent=error.message;}return;}
    }
    if(!order.id)return;
    cancel(); key.value = order.id; input.value = order.numero;
    summary.textContent = identification(order);
    summary.setAttribute('style', 'white-space: pre-line');
    summary.hidden = false; message.dataset.lookupError='0'; input.setCustomValidity(''); message.textContent = 'O.S. selecionada e identificada. Confira os dados antes de salvar.'; input.focus();
    if(root.dataset.completion==='1')root.dispatchEvent(new CustomEvent('pcm-entry-order',{detail:order}));
    if(root.dataset.progress==='1')window.dispatchEvent(new CustomEvent('pcm-order-selected',{detail:order}));
  };
  input.addEventListener('input', () => {
    if(root.dataset.completion==='1')root.dispatchEvent(new CustomEvent('pcm-entry-order',{detail:null}));
    message.dataset.lookupError='0';
    cancel(); const current = version; key.value = ''; summary.textContent = ''; summary.hidden = true; input.setCustomValidity(''); list.replaceChildren();
    const term = input.value.trim();
    if (term.length < 2 || term.length > 60) { message.textContent = 'Digite de 2 a 60 caracteres.'; return; }
    message.dataset.lookupError='0';
    message.textContent = 'Pesquisando O.S.…';
    timer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const url = new URL(root.dataset.lookup, window.location.origin); url.searchParams.set('q', term);
        filters.forEach(name => { const value = input.form.elements[name]?.value; if (value) url.searchParams.set(name, value); });
        const response = await fetch(url, {signal:controller.signal, headers:{Accept:'application/json'}, cache:'no-store'});
        if (current !== version) return;
        if (response.redirected) throw new Error('Entre novamente no sistema para continuar.');
        const data = await response.json(); if (current !== version) return;
        if (!response.ok) throw new Error(data.error || 'Não foi possível pesquisar as O.S.');
        list.replaceChildren();
        data.results.forEach((order,index) => {
          const option = document.createElement('button'); option.type = 'button'; option.id = `${list.id}-${index}`;
          option.setAttribute('role','option'); option.setAttribute('aria-selected','false'); option.className = 'd-block w-100 text-start border-0 border-bottom p-3 bg-white';
          option.setAttribute('style', 'white-space: pre-line');
          option.textContent = identification(order) + (order.aviso ? '\n' + order.aviso : '');
          option.disabled = order.selecionavel !== true; option.setAttribute('aria-disabled', String(option.disabled));
          option.addEventListener('mousedown', event => event.preventDefault()); option.addEventListener('click', () => choose(order)); list.append(option);
          if (!order.selecionavel && root.dataset.link) {
            const link = document.createElement('a'), target = new URL(root.dataset.link, window.location.origin);
            target.searchParams.set('setor_id', order.setor_id); target.searchParams.set('pesquisa', order.numero);
            link.href = target.href; link.target = '_blank'; link.rel = 'noopener'; link.className = 'd-block p-2';
            link.textContent = `PCM: vincular ou conferir a O.S. ${order.numero} no caderno correto (nova aba)`; list.append(link);
          }
        });
        list.hidden = data.results.length === 0; input.setAttribute('aria-expanded', String(!list.hidden));
        message.dataset.lookupError=data.results.length?'0':'1';
        message.textContent = data.has_more ? 'Mostrando os primeiros 20 resultados. Refine a pesquisa.' : (data.results.length ? 'Selecione uma O.S. com as setas e Enter ou clique no resultado.' : data.message);
      } catch (error) { if (error.name !== 'AbortError' && current === version) { close(); message.dataset.lookupError='1'; message.textContent = error.message; } }
    },300);
  });
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') { cancel(); return; }
    const options = [...list.querySelectorAll('button:not(:disabled)')]; if (list.hidden || !options.length) return;
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault(); active = (active + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
      options.forEach((option,index) => option.setAttribute('aria-selected', String(index === active)));
      input.setAttribute('aria-activedescendant', options[active].id); options[active].scrollIntoView({block:'nearest'});
    } else if (event.key === 'Enter') { event.preventDefault(); if (active >= 0) options[active].click(); }
  });
  filters.forEach(name => input.form.elements[name]?.addEventListener('change', clear));
  document.getElementById('os-limpar').addEventListener('click', () => { clear(); input.focus(); });
  document.addEventListener('click', event => { if (!root.contains(event.target)) close(); });
  input.form.addEventListener('submit', event => {
    if (!key.value) { event.preventDefault(); input.setCustomValidity('Pesquise e selecione uma O.S. válida.'); input.reportValidity(); }
  });
})();
