'use strict';
(() => {
    const form=document.getElementById('evolucao-form');
    if (!form) return;
    let dirty=0, submitted=false;
    form.querySelectorAll('[data-percentual]').forEach(input => {
        input.addEventListener('input',()=>{
            const active=input.value.trim()!=='';
            const row=input.closest('tr');
            row.querySelectorAll('[data-row-field]').forEach(field=>field.disabled=!active);
            const output=row.querySelector('[data-avanco]');
            const value=Number(input.value), previous=Number(input.dataset.anterior);
            if (active && Number.isFinite(value) && value>=0 && value<=100) {
                const delta=Math.round((value-previous)*100)/100;
                output.textContent='Avanço calculado automaticamente: '+(delta>0?'+':'')+delta.toLocaleString('pt-BR')+' p.p.'+(delta===0?' — verificado sem avanço.':'');
            } else { output.textContent='Avanço calculado automaticamente: —'; }
            dirty=Array.from(form.querySelectorAll('[data-percentual]')).filter(f=>f.value.trim()!=='').length;
            document.getElementById('evolucao-dirty').textContent=dirty+' alteração(ões) não salva(s).';
        });
    });
    form.addEventListener('submit',event=>{
        if (!dirty || !window.confirm('Confirma o registro de '+dirty+' percentual(is) acumulado(s)?')) { event.preventDefault(); return; }
        form.querySelectorAll('[data-percentual]').forEach(input=>{input.disabled=input.value.trim()==='';});
        submitted=true;
    });
    window.addEventListener('beforeunload',event=>{if(dirty&&!submitted){event.preventDefault();event.returnValue='';}});
})();
