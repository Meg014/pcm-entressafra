'use strict';
(() => {
    const form=document.getElementById('porcentagem-rapida');if(!form)return;
    const percent=document.getElementById('rapida-percentual'),revision=document.getElementById('rapida-revisao'),reference=document.getElementById('rapida-referencia'),output=document.getElementById('rapida-avanco');
    const delta=()=>{const v=Number(percent.value),p=Number(percent.dataset.anterior);output.textContent=percent.value!==''&&Number.isFinite(v)&&v>=0&&v<=100?'Avanço calculado: '+(v-p>0?'+':'')+(Math.round((v-p)*100)/100).toLocaleString('pt-BR')+' pontos percentuais':'Avanço calculado: —';};
    const retro=document.getElementById('rapida-retroativa');
    const history=document.getElementById('rapida-historico'),historyLink=document.getElementById('rapida-historico-link');
    const select=order=>{
        if(!order)percent.value='';
        revision.value=order?.revisao??'';reference.value=order?.referencia??'';percent.dataset.anterior=order?.percentual??0;
        document.getElementById('rapida-ultimo').textContent=(order?.percentual??0)+'%';document.getElementById('rapida-semana').textContent=order?.referencia??'Selecione a O.S.';
        if(retro){retro.replaceChildren(new Option('Referência automática',''));(order?.referencias??[]).filter(d=>d<=order.referencia).forEach(d=>retro.add(new Option(d,d)));}
        if(history){history.hidden=!order;if(order)historyLink.href=historyLink.dataset.template.replace(/1$/,encodeURIComponent(order.id));}
        delta();
    };
    window.addEventListener('pcm-order-selected',event=>select(event.detail));
    document.getElementById('os-pesquisa').addEventListener('input',()=>select(null));
    percent.addEventListener('input',delta);
    select(JSON.parse(form.dataset.initial||'null'));
    retro?.addEventListener('change',()=>{const id=document.getElementById('atividade-selecionada').value;if(id){const url=new URL(retro.dataset.url,window.location.origin);url.searchParams.set('atividade_id',id);if(retro.value)url.searchParams.set('referencia',retro.value);window.location.assign(url);}});
    form.addEventListener('submit',event=>{if(!revision.value||!reference.value){event.preventDefault();output.textContent='Pesquise e selecione uma O.S. válida.';}});
})();
