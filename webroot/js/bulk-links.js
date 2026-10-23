(() => {
  const root = document.getElementById('bulk-links'); if (!root) return;
  const el = name => document.getElementById(`bulk-${name}`), form = el('form'), fields = form.elements;
  let token = root.dataset.token || '', data, busy = false, running = false, page = 1, timer, serviceVersion = 0;
  const message = (text, error = false) => { el('message').textContent = text; el('message').className = `alert alert-${error ? 'danger' : 'info'}`; };
  const scope = () => Object.fromEntries(['entressafra_id','setor_id','caderno_id'].map(key => [key,fields[key].value]));
  const request = async (action, payload = {}, post = false) => {
    const url = new URL(root.dataset.api, location.origin), params = {acao:action,token,...payload};
    const options = {headers:{Accept:'application/json'},cache:'no-store'};
    if (post) { options.method = 'POST'; options.headers['Content-Type'] = 'application/json'; options.headers['X-CSRF-Token'] = fields._csrfToken.value; options.body = JSON.stringify(params); }
    else Object.entries(params).forEach(([key,value]) => url.searchParams.set(key,value));
    const response = await fetch(url,options);
    if (response.redirected) throw new Error('Entre novamente no sistema. Depois retome a prévia pelo endereço desta página.');
    const result = await response.json(); if (!response.ok) throw new Error(result.error || 'Operação indisponível.'); return result;
  };
  const lockForm = () => { [...form.querySelectorAll('select,input:not([type="hidden"])')].forEach(input => input.disabled = !!token || busy); el('preview').disabled = busy || !!token; el('new').hidden = !token; el('new').disabled = busy || running; };
  const cell = (row, value) => { const td = document.createElement('td'); td.textContent = value ?? ''; row.append(td); return td; };
  const render = result => {
    data = result; page = data.pagina; el('results').hidden = false; lockForm();
    ['entressafra_id','setor_id','caderno_id','cc','bem','busca'].forEach(key => fields[key].value = String(data.filtros[key] ?? ''));
    [...fields.caderno_id.options].forEach(option => { if (option.value) option.hidden = (fields.setor_id.value && !(option.dataset.setores || '').split(',').includes(fields.setor_id.value)) || option.dataset.entressafra !== fields.entressafra_id.value; });
    el('services').replaceChildren();
    data.filtros.servicos.forEach(code => { const option = document.createElement('option'); option.value = code; option.textContent = code; option.selected = true; el('services').append(option); });
    const preparing = data.estado === 'preparando', processing = data.estado === 'processando', editable = data.estado === 'pronto' && !busy;
    el('title').textContent = data.estado === 'concluido' ? 'Resultado da vinculação' : `Prévia — ${data.destino}`;
    el('scope').textContent = `Entressafra: ${data.entressafra_nome} · Setor: ${data.setor_nome.replace(/^TOTVS\s+\d+\s*\/\s*/, '')} · Caderno: ${data.destino} · Serviços: ${data.filtros.servicos.join(', ')} · Estado: ${data.estado}`;
    el('counts').replaceChildren();
    const labels = {encontradas:'Encontradas',selecionadas:'Selecionadas',elegiveis:'Elegíveis pendentes',ja_vinculadas:'Já vinculadas',bloqueadas:'Bloqueadas na prévia',vinculadas:'Vinculadas agora',falhas:'Não vinculadas após confirmação'};
    Object.entries(data.contagens).forEach(([key,value]) => { const badge = document.createElement('span'); badge.className = 'badge bg-light text-dark border'; badge.textContent = `${labels[key]}: ${value}`; el('counts').append(badge); });
    el('rows').replaceChildren();
    data.rows.forEach(item => {
      const row = document.createElement('tr'), checkbox = document.createElement('input'); checkbox.type = 'checkbox'; checkbox.className = 'form-check-input'; checkbox.checked = item.selecionado; checkbox.disabled = !editable || item.estado !== 'elegivel'; checkbox.setAttribute('aria-label',`Selecionar O.S. ${item.os.numero}`);
      checkbox.addEventListener('change',() => operate(async () => render(await request('selecionar',{ids:[item.id],selecionado:checkbox.checked,busca:el('search').value,pagina:page},true))));
      cell(row,'').append(checkbox); cell(row,item.os.numero); cell(row,item.os.descricao || 'Descrição não informada'); cell(row,`${item.os.rotulo_setor || 'Setor'}: ${item.os.setor_apresentacao || 'Não definido'}`); cell(row,item.os.bem);
      const date = item.os.data_origem; cell(row,date && date.length === 8 ? `${date.slice(6,8)}/${date.slice(4,6)}/${date.slice(0,4)}` : date);
      cell(row,`${item.os.situacao} / término ${item.os.termino}`); cell(row,[item.caderno_atual,item.estado === 'sucesso' ? 'Vinculada com sucesso' : item.motivo].filter(Boolean).join(' · ')); el('rows').append(row);
    });
    if (!data.rows.length) { const row = document.createElement('tr'); cell(row,'Nenhuma O.S. encontrada para estes filtros.').colSpan = 8; el('rows').append(row); }
    el('pages').textContent = `Página ${data.pagina} de ${data.paginas} · ${data.filtradas} O.S. nesta pesquisa`;
    el('prev').disabled = busy || page <= 1; el('next').disabled = busy || page >= data.paginas;
    el('all').disabled = !editable || data.elegiveis_filtradas === 0; el('none').disabled = !editable;
    el('confirm').hidden = data.estado !== 'pronto'; el('confirm').disabled = !editable || !data.contagens.selecionadas;
    el('resume').hidden = running || (!preparing && !processing); el('resume').disabled = busy;
    el('stop').hidden = !running;
    el('search').disabled = busy || preparing || processing;
  };
  async function operate(action) {
    if (busy) return; busy = true; if (data) render(data); else lockForm();
    try { await action(); } catch (error) { running = false; message(error.message,true); }
    finally { busy = false; if (data) render(data); else lockForm(); }
  }
  const load = async () => render(await request('previa',{busca:el('search').value,pagina:page}));
  const run = async () => {
    running = true;
    while (running && ['preparando','processando'].includes(data.estado)) {
      message(data.estado === 'preparando' ? 'Consultando O.S. e preparando a prévia. Nenhum vínculo será criado nesta fase.' : 'Vinculando O.S. em pequenos lotes. Aguarde ou pare após o lote atual.');
      render(await request(data.estado === 'preparando' ? 'preparar' : 'processar',{},true));
    }
    running = false;
    message(data.estado === 'pronto' ? 'Prévia pronta. Confira o destino, selecione as O.S. e confirme a quantidade.' : data.estado === 'concluido' ? 'Operação concluída. Confira as quantidades e os motivos de itens não vinculados.' : 'Operação pausada após o lote atual. Use Retomar para continuar.');
  };
  const updateNotebooks = () => {
    ++serviceVersion; el('services').replaceChildren();
    [...fields.caderno_id.options].forEach(option => { if (option.value) option.hidden = (fields.setor_id.value && !(option.dataset.setores || '').split(',').includes(fields.setor_id.value)) || option.dataset.entressafra !== fields.entressafra_id.value; });
    if (fields.caderno_id.selectedOptions[0]?.hidden) fields.caderno_id.value = '';
  };
  const filterSectors = () => {
    const notebook = [...fields.caderno_id.options].find(option => option.value === fields.caderno_id.value);
    const ids = notebook?.dataset.setores?.split(',') || [];
    [...fields.setor_id.options].forEach(option => { if (option.value) option.hidden = !!fields.caderno_id.value && !ids.includes(option.value); });
    if (fields.setor_id.selectedOptions[0]?.hidden) fields.setor_id.value = '';
  };
  const services = async () => {
    const current = ++serviceVersion; el('services').replaceChildren();
    if (Object.values(scope()).some(value => !value)) return;
    el('services-help').textContent = 'Consultando serviços disponíveis no TOTVS…';
    try {
      const result = await request('servicos',scope()); if (current !== serviceVersion) return;
      result.servicos.forEach(service => { const option = document.createElement('option'); option.value = service.codigo; option.textContent = `${service.codigo} — ${service.nome} (${service.quantidade} O.S.)`; el('services').append(option); });
      el('services-help').textContent = result.servicos.length ? 'Selecione até 20 serviços. Códigos antigos são incluídos quando elegíveis.' : 'Nenhum serviço elegível nesta equipe.';
    } catch (error) { if (current === serviceVersion) el('services-help').textContent = error.message; }
  };
  ['entressafra_id','setor_id'].forEach(key => fields[key].addEventListener('change',() => { updateNotebooks(); filterSectors(); services(); }));
  fields.caderno_id.addEventListener('change',() => { filterSectors(); services(); });
  form.addEventListener('submit',event => {
    event.preventDefault(); if (!form.reportValidity()) return;
    operate(async () => {
      const payload = {...scope(),servicos:[...el('services').selectedOptions].map(option=>option.value)};
      ['cc','bem','busca'].forEach(key => payload[key] = fields[key].value);
      const created = await request('criar',payload,true); token = created.token;
      const url = new URL(location.href); url.searchParams.set('lote',token); history.replaceState(null,'',url);
      data = await request('previa'); await run();
    });
  });
  el('all').addEventListener('click',() => {
    if (!data || !confirm(`Selecionar TODAS as ${data.elegiveis_filtradas} O.S. elegíveis da pesquisa, inclusive as de outras páginas? Isso ainda não cria vínculos.`)) return;
    operate(async () => render(await request('selecionar',{todas:true,selecionado:true,quantidade_confirmada:data.elegiveis_filtradas,busca:el('search').value,pagina:page},true)));
  });
  el('none').addEventListener('click',() => operate(async () => render(await request('selecionar',{todas:true,selecionado:false,busca:'',pagina:page},true))));
  el('confirm').addEventListener('click',() => {
    if (!data || !confirm(`CONFIRMAR a vinculação de ${data.contagens.selecionadas} O.S. ao caderno “${data.destino}” (entressafra ${data.entressafra_nome}, setor ${data.setor_nome.replace(/^TOTVS\s+\d+\s*\/\s*/, '')})? Confira se todas pertencem a este caderno. Nenhuma atividade existente será transferida.`)) return;
    operate(async () => { render(await request('confirmar',{quantidade_confirmada:data.contagens.selecionadas},true)); await run(); });
  });
  el('resume').addEventListener('click',() => operate(run)); el('stop').addEventListener('click',() => { running = false; });
  el('prev').addEventListener('click',() => operate(async () => { --page; await load(); })); el('next').addEventListener('click',() => operate(async () => { ++page; await load(); }));
  el('search').addEventListener('input',() => { clearTimeout(timer); timer = setTimeout(() => operate(async () => { page = 1; await load(); }),300); });
  el('new').addEventListener('click',() => { if (busy || running) return; location.href = location.pathname; });
  updateNotebooks(); if (token) operate(load);
})();
