window.addEventListener('load',()=>setTimeout(()=>{
 const results=[],check=(ok,message)=>{if(!ok)throw Error(message);results.push(message);};
 try{
  const root=document.getElementById('porcentagens-lote'),scroll=root.querySelector('.weekly-sheet-scroll'),headers=[...root.querySelectorAll('[data-week-header]')];
  check(headers.length>20,'Calendário longo exibe todas as semanas iniciadas');
  check(root.querySelectorAll('thead .week-current').length===1,'Uma única semana atual');
  check(scroll.scrollWidth>scroll.clientWidth,'Semanas acessíveis por rolagem horizontal');
  const first=root.querySelector('tbody .fixed-os'),before=first.getBoundingClientRect().left;scroll.scrollLeft=scroll.scrollWidth;
  check(Math.abs(first.getBoundingClientRect().left-before)<1,'Número da O.S. permanece fixo durante rolagem');
  const last=headers[headers.length-1].getBoundingClientRect();check(last.left<scroll.getBoundingClientRect().right,'Última semana pode ser visualizada');
  const current=root.querySelector('thead .week-current');check(current.dataset.weekHeader==='2026-11-02','Não exibe semanas futuras no calendário de teste');
  check(root.querySelectorAll('[data-percentage]').length===headers.length*4,'Células geradas dinamicamente para cada O.S. e semana');
  check(document.documentElement.scrollWidth<=innerWidth,'Página sem overflow horizontal');check(window.errors.length===0,'Sem erros JavaScript');
 }catch(error){results.push('FAIL: '+error.message);}
 const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:innerWidth,results,errors:window.errors});document.body.append(output);
},100));
