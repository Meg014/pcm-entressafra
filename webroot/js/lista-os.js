'use strict';
(() => {
    const form=document.getElementById('lista-numeros');
    if(form){const input=form.elements.numeros,output=document.getElementById('lista-contagem');const update=()=>{const tokens=input.value.trim().split(/[\s,;]+/).filter(Boolean);const numbers=tokens.map(v=>v.padStart(6,'0'));const unique=new Set(numbers).size;output.textContent=`Informadas: ${tokens.length} · Únicas: ${unique} · Repetidas: ${tokens.length-unique}`;};input.addEventListener('input',update);update();}
    const process=document.querySelector('form[data-auto-process]');
    if(process){
        let submitting=false;
        process.addEventListener('submit',event=>{
            if(submitting){event.preventDefault();return;}
            submitting=true;
            process.querySelectorAll('button').forEach(button=>{button.disabled=true;});
        });
        if(process.dataset.autoProcess==='1')window.setTimeout(()=>{if(!submitting)process.requestSubmit();},400);
    }
})();
