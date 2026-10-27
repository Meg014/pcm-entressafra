
(async()=>{
const results=[];const check=(v,m)=>{if(!v)throw Error(m);results.push(m);};
try{
const lines=document.getElementById('entry-lines'),add=document.getElementById('add-entry');
const orderRoot=document.getElementById('os-autocomplete'),save=document.getElementById('entry-save');
check(!save.disabled,'Percentual não bloqueia o botão salvar');
for(const percentage of [0,10,50,99,100]){
 orderRoot.dispatchEvent(new CustomEvent('pcm-entry-order',{detail:{percentual_atual:percentage}}));
 check(!save.disabled&&document.getElementById('entry-order-status').textContent.includes(percentage+'%'),'Percentual '+percentage+'% informativo e sem bloqueio');
}
check(!document.getElementById('entry-order-status').className.includes('alert'),'Sem aviso de bloqueio por percentual');
check(lines.children.length===1,'Começa com uma linha');
const first=lines.firstElementChild,fields=first.querySelectorAll('[data-mask]');
check(add.textContent.trim()==='+','Adicionar usa apenas +');
check(first.querySelector('.remove-entry').textContent.trim()==='−','Remover usa apenas −');
check(getComputedStyle(document.getElementById('os-aviso')).display==='none','Sem instruções abaixo da O.S.');
check(getComputedStyle(first.querySelector('[id$=funcionario-aviso]')).display==='none','Sem instruções abaixo do funcionário');
check(getComputedStyle(first.querySelector('.entry-duration')).display==='none','Sem texto de duração abaixo da linha');
if(window.innerWidth>=760)check(first.getBoundingClientRect().height<65,'Linha compacta de planilha');
['22102026','0700','1100'].forEach((v,i)=>{fields[i].value=v;fields[i].dispatchEvent(new Event('input'));});
check(fields[0].value==='22/10/2026'&&fields[1].value==='07:00','Máscaras de data e hora');
check(first.querySelector('.entry-duration').textContent.includes('4h 0min'),'Duração de quatro horas');
check(fields.length===5&&[...fields].slice(1).every(f=>!f.required),'Quatro horários opcionais');
const setHours=values=>values.forEach((v,i)=>{fields[i+1].value=v;fields[i+1].dispatchEvent(new Event('input'));});
setHours(['','','1200','1400']);check([...fields].every(f=>f.checkValidity())&&first.querySelector('.entry-duration').textContent.includes('2h 0min'),'Aceita somente tarde');
setHours(['0700','1100','1200','1700']);check([...fields].every(f=>f.checkValidity())&&first.querySelector('.entry-duration').textContent.includes('9h 0min'),'Soma períodos sem intervalo');
setHours(['0700','1300','1200','1700']);check(!fields[3].checkValidity(),'Bloqueia sobreposição dos períodos');
setHours(['','','1200','']);check(!fields[4].checkValidity(),'Exige par completo no período');
setHours(['','','','']);check(!fields[1].checkValidity(),'Exige pelo menos um período');
setHours(['0700','1100','','']);
first.querySelector('textarea').value='Preservar serviço';add.click();add.click();
check(lines.children.length===3,'Adiciona linhas independentes');
check(first.querySelector('textarea').value==='Preservar serviço','Preserva dados ao adicionar');
const roots=[...lines.querySelectorAll('[data-lookup]')];
const input=roots[1].querySelector('[role=combobox]');input.value='teste';input.dispatchEvent(new Event('input'));
await new Promise(r=>setTimeout(r,400));
const options=roots[1].querySelectorAll('[role=option]');check(options.length===2&&options[1].disabled,'Autocomplete bloqueia funcionário não NORMAL');options[0].click();
check(roots[1].querySelector('[id$=funcionario-chave]').value==='0001','Seleciona funcionário na própria linha');
check(roots[0].querySelector('[id$=funcionario-chave]').value==='','Identidades independentes');
lines.children[1].querySelector('.remove-entry').click();check(lines.children.length===2,'Remove linha');
check(lines.children[1].querySelector('.entry-number span').textContent==='2','Renumera linhas');
check(first.querySelector('textarea').value==='Preservar serviço','Remover preserva demais dados');
fields[2].value='0600';fields[2].dispatchEvent(new Event('input'));check(!fields[2].checkValidity(),'Bloqueia hora final menor');
fields[2].value='0700';fields[2].dispatchEvent(new Event('input'));check(!fields[2].checkValidity(),'Bloqueia duração zero');
fields[2].value='1100';fields[2].dispatchEvent(new Event('input'));check(fields[2].checkValidity(),'Corrigir limpa erro');
const ids=[...document.querySelectorAll('[id]')].map(e=>e.id);check(new Set(ids).size===ids.length,'IDs únicos');
check(document.documentElement.scrollWidth<=window.innerWidth,'Sem overflow horizontal');
add.click();lines.lastElementChild.querySelector('.remove-entry').click();
document.getElementById('atividade-selecionada').value='1';
[...lines.querySelectorAll('[id$=funcionario-chave]')].forEach(f=>f.value='0001');
const submit=new Event('submit',{cancelable:true});document.getElementById('batch-form').dispatchEvent(submit);
check(!submit.defaultPrevented,'Linha removida vazia não bloqueia envio');
check(lines.children[1].querySelector('input').name==='apontamentos[1][funcionario_rotulo]','Reindexa nomes antes do envio');
check(document.getElementById('entry-count').value==='2','Quantidade enviada corresponde às linhas visíveis');
check(window.errors.length===0,'Sem erros JavaScript');
}catch(e){results.push('FAIL: '+e.message);}
const output=document.createElement('pre');output.id='browser-test-results';output.textContent=JSON.stringify({width:window.innerWidth,results,errors:window.errors});document.body.append(output);
})();
