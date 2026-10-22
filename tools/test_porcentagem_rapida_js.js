function testPorcentagemRapida(source) {
    let assertions=0;const assert=(v,m)=>{assertions++;if(!v)throw new Error(m);};
    const elements={};const make=()=>{let value='';return {dataset:{},textContent:'',events:{},get value(){return value;},set value(v){value=String(v);},addEventListener(name,fn){this.events[name]=fn;}};};
    ['porcentagem-rapida','rapida-percentual','rapida-revisao','rapida-referencia','rapida-avanco','rapida-ultimo','rapida-semana','os-pesquisa'].forEach(id=>elements[id]=make());
    const form=elements['porcentagem-rapida'];form.dataset.initial='null';const events={};
    new Function('document','window',source)({getElementById:id=>elements[id]??null},{addEventListener:(name,fn)=>events[name]=fn});
    let prevented=false;form.events.submit({preventDefault(){prevented=true;}});assert(prevented,'Não pode salvar sem seleção.');
    events['pcm-order-selected']({detail:{percentual:20,anterior:20,revisao:0,referencia:'2026-10-19'}});
    assert(elements['rapida-ultimo'].textContent==='20%','Exibe o último acumulado.');assert(elements['rapida-revisao'].value==='0','Preserva revisão zero para concorrência.');
    elements['rapida-percentual'].value=30;elements['rapida-percentual'].events.input();assert(elements['rapida-avanco'].textContent.includes('+10 pontos'),'30 após 20 corresponde a +10 p.p.');
    elements['rapida-percentual'].value=20;elements['rapida-percentual'].events.input();assert(elements['rapida-avanco'].textContent.includes('0 pontos'),'Confirmação sem avanço.');
    events['pcm-order-selected']({detail:{percentual:10,anterior:0,revisao:1,referencia:'2026-10-19'}});
    elements['rapida-percentual'].value=80;elements['rapida-percentual'].events.input();assert(elements['rapida-avanco'].textContent.includes('+70 pontos'),'80 após o último acumulado 10 é +70 p.p., mesmo com anterior semanal zero.');
    prevented=false;form.events.submit({preventDefault(){prevented=true;}});assert(!prevented,'Pode salvar uma seleção válida mesmo com revisão zero.');
    elements['os-pesquisa'].events.input();assert(elements['rapida-revisao'].value==='','Editar a pesquisa invalida a seleção.');
    prevented=false;form.events.submit({preventDefault(){prevented=true;}});assert(prevented,'Nome/número livre não pode ser salvo.');
    return {status:'OK',assertions};
}
