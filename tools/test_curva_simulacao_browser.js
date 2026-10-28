window.addEventListener('load',()=>setTimeout(()=>{
 const results=[],check=(ok,message)=>{if(!ok)throw Error(message);results.push(message);};
 try{
  const root=document.querySelector('#curva-semanal'),svg=root.querySelector('svg');
  check(root.dataset.simulation==='true','Modo simulação identificado');
  check(document.querySelector('[data-simulation-banner]').textContent.includes('SIMULAÇÃO — DADOS FICTÍCIOS'),'Aviso de dados fictícios');
  check(svg.textContent.includes('SIMULAÇÃO — DADOS FICTÍCIOS'),'Gráfico identificado inclusive em captura');
  check(root.querySelector('.curve-legend').textContent.includes('Realizado simulado')&&root.querySelector('.curve-legend').textContent.includes('Tendência simulada'),'Legenda identifica valores simulados');
  const n=svg.querySelectorAll('[data-week]').length;
  check(n>17,'Calendário real com mais de 17 semanas');
  const actual=[...svg.querySelectorAll('[data-point-series=realizado]')].map(el=>Number(el.dataset.value));
  const planned=[...svg.querySelectorAll('[data-point-series=programado]')].map(el=>Number(el.dataset.value));
  check(actual.length===n,'Realizado em todas as semanas');
  check(!/\d+[,.]\d+%/.test(root.textContent),'Simulação exibe percentuais inteiros inclusive tooltips e tabela');
  check([...svg.querySelectorAll('[data-point-label]')].every(el=>/^\d+%$/.test(el.textContent)&&parseInt(el.textContent,10)<=100),'Rótulos inteiros limitados a 100%');
  check([...svg.querySelectorAll('[data-point-series]')].every(el=>Math.abs(Number(el.getAttribute('cy'))-(330-Number(el.dataset.value)*2.7))<.000001),'Forma das linhas preserva precisão interna');
  check(actual.every((v,i)=>v>=0&&v<=100&&(!i||v>=actual[i-1])),'Realizado crescente entre 0 e 100');
  check(actual[0]<=2&&actual[n-1]>=99,'Evolução completa de próximo de zero a 100');
  check(actual.some((v,i)=>v<planned[i]-.001)&&actual.some((v,i)=>v>planned[i]+.001),'Semanas abaixo e acima do programado');
  check(svg.querySelectorAll('[data-point-series=tendencia]').length===n-3,'Tendência somente após três apurações simuladas');
  check([...svg.querySelectorAll('[data-series]')].every(el=>el.getAttribute('d').length>0),'Três linhas completas no gráfico');
  const labels=[...svg.querySelectorAll('[data-point-label]')].filter(el=>getComputedStyle(el).visibility!=='hidden');
  check(svg.dataset.labelsReady==='1'&&labels.length>=n,'Percentuais visíveis');
  const boxes=labels.map(el=>el.getBBox());
  check(boxes.every((a,i)=>boxes.every((b,j)=>i===j||a.x>=b.x+b.width||a.x+a.width<=b.x||a.y>=b.y+b.height||a.y+a.height<=b.y)),'Percentuais sem sobreposição');
  check(root.querySelectorAll('.curve-history tbody tr').length===n&&root.querySelector('.curve-history').textContent.includes('Dado fictício'),'Tabela simulada completa e identificada');
  const bar=root.querySelector('.curve-day-bar');
  check(+bar.dataset.total===+bar.dataset.elapsed+(+bar.dataset.remaining),'Barra de dias consistente');
  const back=document.querySelector('[data-return-real]');
  check(back.textContent==='Voltar aos dados reais'&&!back.href.includes('simular='),'Retorno sem parâmetro de simulação');
  check(document.querySelectorAll('input[name=simular]').length===0,'Filtros não propagam estado de simulação');
  check(document.documentElement.scrollWidth<=innerWidth,'Sem overflow da página');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:innerWidth,results,errors:window.errors});document.body.append(output);
},250));
