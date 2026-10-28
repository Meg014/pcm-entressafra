window.addEventListener('load',()=>setTimeout(()=>{
 const results=[],check=(ok,message)=>{if(!ok)throw Error(message);results.push(message);};
 try{
  const root=document.querySelector('#curva-semanal'),svg=root.querySelector('svg');
  const weeks=[...svg.querySelectorAll('[data-week]')];
  check(weeks.length>=20,'Calendário longo com todas as semanas');
  check(svg.textContent.includes('/26')&&svg.textContent.includes('/27'),'Virada de ano identificada');
  const dateLabels=[...svg.querySelectorAll('.curve-axis-week')].map(el=>el.getBBox());
  check(dateLabels.every((a,i)=>!i||a.x>dateLabels[i-1].x+dateLabels[i-1].width),'Semanas parciais sem sobreposição no eixo');
  const bar=root.querySelector('.curve-day-bar');
  check(+bar.dataset.total===+bar.dataset.elapsed+(+bar.dataset.remaining),'Dias consistentes no calendário real');
  const labels=[...svg.querySelectorAll('[data-point-label]')].filter(el=>getComputedStyle(el).visibility!=='hidden'),boxes=labels.map(el=>el.getBBox());
  check(labels.length>=weeks.length,'Percentuais semanais visíveis');
  check(boxes.every((a,i)=>boxes.every((b,j)=>i===j||a.x>=b.x+b.width||a.x+a.width<=b.x||a.y>=b.y+b.height||a.y+a.height<=b.y)),'Percentuais sem sobreposição no calendário longo');
  check(root.querySelector('.curve-chart-scroll').scrollWidth>root.querySelector('.curve-chart-scroll').clientWidth,'Rolagem preserva as semanas futuras');
  check(document.documentElement.scrollWidth<=innerWidth,'Página sem overflow');
  check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:innerWidth,results,errors:window.errors});document.body.append(output);
},250));
