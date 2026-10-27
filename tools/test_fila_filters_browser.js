(() => {
 const results=[];const check=(value,message)=>{if(!value)throw Error(message);results.push(message);};
 try {
  const form=document.getElementById('fila-filtros');
  const sector=form.elements.setor,progress=form.elements.evolucao;
  check(form.method==='get','Filtros usam consulta GET');
  check(sector.options[0].text==='Todos os setores'&&sector.options[0].value==='','Opção padrão de setor');
  check(progress.options[0].text==='Todas as porcentagens'&&progress.options[0].value==='','Opção padrão de evolução');
  check([...progress.options].map(o=>o.value).join(',')===',zero,andamento,concluidas','Faixas de evolução disponíveis');
  check(sector.selectedOptions[0].text==='MOENDA'&&progress.value==='concluidas','Seleção permanece na segunda página');
  const selected=new FormData(form);
  check(selected.get('setor')===sector.value&&selected.get('evolucao')==='concluidas','Envio combina os filtros');
  check(!selected.has('pagina'),'Nova pesquisa começa na primeira página');
  const clear=[...form.querySelectorAll('a')].find(a=>a.textContent==='Limpar filtros');
  check(!!clear&&!clear.getAttribute('href').includes('setor=')&&!clear.getAttribute('href').includes('evolucao='),'Limpeza remove os filtros');
  check([...form.querySelectorAll('button')].some(b=>b.textContent==='Filtrar'),'Botão Filtrar disponível');
  if(window.innerWidth>=576)check(Math.abs(sector.getBoundingClientRect().top-progress.getBoundingClientRect().top)<2,'Campos lado a lado');
  const table=document.querySelector('table');
  check(table.querySelectorAll('thead th').length===9,'Preserva as nove colunas da fila');
  check(table.querySelector('a').textContent==='Ver registros','Preserva acesso aos registros');
  check(document.querySelector('nav[aria-label="Paginação"]').textContent.includes('Página 2 de 2'),'Paginação filtrada correta');
  const previous=document.querySelector('nav[aria-label="Paginação"] a').getAttribute('href');
  check(previous.includes('setor=')&&previous.includes('evolucao=concluidas')&&previous.includes('pagina=1'),'Paginação mantém os dois filtros');
  check(document.documentElement.scrollWidth<=window.innerWidth,'Sem overflow horizontal');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:window.innerWidth,results,errors:window.errors});document.body.append(output);
})();
