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
    const transfers=document.getElementById('transferencias-selecao');
    if(transfers){
        let dirty=false,busy=false;const notice=document.createElement('p');notice.setAttribute('role','alert');notice.className='small text-danger';transfers.append(notice);
        const boxes=[...transfers.querySelectorAll('input[type=checkbox]')],initialVisible=boxes.filter(box=>box.checked).length;
        const updateCount=()=>{const count=Number(transfers.dataset.selectedTotal)-initialVisible+boxes.filter(box=>box.checked).length;document.querySelectorAll('[data-transfer-total]').forEach(label=>label.textContent=String(count));document.getElementById('transferencias-revisar').disabled=count===0;};
        transfers.addEventListener('change',()=>{dirty=true;updateCount();});updateCount();
        document.querySelectorAll('[data-transfer-page],nav[aria-label="Paginação"] a').forEach(link=>link.addEventListener('click',async event=>{
            if(!dirty)return;event.preventDefault();if(busy)return;busy=true;
            try{const payload=new FormData(transfers);payload.set('acao','selecionar_transferencias');const response=await fetch(transfers.action,{method:'POST',body:payload,headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':transfers.elements._csrfToken.value}});
                if(response.redirected||!response.ok)throw Error('Não foi possível guardar a seleção. Confira sua sessão.');const result=await response.json();if(!result.ok)throw Error('Seleção não confirmada.');dirty=false;location.assign(link.href);
            }catch(error){notice.textContent=error.message+' A seleção desta página foi mantida.';}finally{busy=false;}
        }));
    }
    const dialog=document.getElementById('transferencias-confirmacao');
    if(dialog){if(typeof dialog.showModal==='function'){dialog.close();dialog.showModal();}dialog.querySelector('[data-transfer-cancel]').addEventListener('click',event=>{event.preventDefault();dialog.close();});}
})();
