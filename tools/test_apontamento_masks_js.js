function testApontamentoMasks(source) {
    let assertions=0;const check=(v,m)=>{assertions++;if(!v)throw new Error(m);};
    const field=()=>({value:'',selectionStart:0,selectionEnd:0,events:{},validity:'',addEventListener(n,fn){this.events[n]=fn;},setCustomValidity(v){this.validity=v;},setSelectionRange(a,b){this.selectionStart=a;this.selectionEnd=b;}});
    const date=field(),start=field(),end=field(),afternoonStart=field(),afternoonEnd=field(),next={checked:false,events:{},addEventListener(n,fn){this.events[n]=fn;}},out={textContent:''};
    const form={elements:{data_servico:date,hora_inicio_manha:start,hora_fim_manha:end,hora_inicio_tarde:afternoonStart,hora_fim_tarde:afternoonEnd},querySelector:()=>next};
    new Function('document',source)({getElementById:id=>id==='apontamento-form'?form:out});
    const input=(f,v)=>{f.value=v;f.selectionStart=v.length;f.selectionEnd=v.length;f.events.input();};
    for(const c of '22102026'){input(date,date.value+c);}check(date.value==='22/10/2026','Digitação incremental da data.');
    input(start,'0730');input(end,'1130');check(start.value==='07:30'&&end.value==='11:30','Máscaras HH:MM.');check(out.textContent.includes('4h 0min'),'Duração de quatro horas.');
    check(!date.events.keydown&&!start.events.keydown&&!end.events.keydown,'Não intercepta Tab.');
    const paste=(f,text)=>{f.selectionStart=0;f.selectionEnd=f.value.length;let blocked=false;f.events.paste({preventDefault(){blocked=true;},clipboardData:{getData:()=>text}});check(blocked,'Colagem tratada pela máscara.');};
    paste(date,'29/02/2028');check(date.value==='29/02/2028'&&!date.validity,'Colagem de data bissexta válida.');paste(start,'0730');check(start.value==='07:30','Colagem de hora sem separador.');
    paste(date,'31/02/2026');check(!!date.validity,'Data inexistente não aceita.');input(date,'22102026');
    input(start,'2460');check(!!start.validity,'Hora e minuto inválidos não aceitos.');
    input(start,'2300');input(end,'0200');check(!!end.validity&&!next.checked,'Virada não é presumida automaticamente.');
    next.checked=true;input(end,'0200');check(!!end.validity,'Virada permanece bloqueada.');
    next.checked=false;input(start,'0700');input(end,'0700');check(!!end.validity,'Duração zero não permitida.');
    input(end,'1130');check(!end.validity,'Correção do valor limpa a validação anterior.');
    date.selectionStart=3;date.selectionEnd=5;date.events.paste({preventDefault(){},clipboardData:{getData:()=>'11'}});check(date.value==='22/11/2026','Colagem substitui somente o trecho selecionado.');
    input(end,'');check(!!end.validity,'Apagar mantém o cálculo em estado incompleto.');
    input(start,'');input(afternoonStart,'1200');input(afternoonEnd,'1400');check(!start.validity&&!end.validity&&out.textContent.includes('2h 0min'),'Somente tarde é aceita.');
    input(start,'0700');input(end,'1100');input(afternoonEnd,'1700');check(out.textContent.includes('9h 0min'),'Soma dos períodos sem almoço.');
    input(afternoonStart,'1000');check(!!afternoonStart.validity,'Sobreposição bloqueada.');
    input(afternoonStart,'1200');input(afternoonEnd,'');check(!!afternoonEnd.validity,'Período incompleto bloqueado.');
    return {status:'OK',assertions};
}
