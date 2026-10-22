'use strict';
(() => {
  const form=document.getElementById('apontamento-form');if(!form)return;
  const date=form.elements.data_servico,start=form.elements.hora_inicial,end=form.elements.hora_final;
  const next=form.querySelector('input[type="checkbox"][name="termina_dia_seguinte"]'),output=document.getElementById('horas-calculadas');
  const digits=value=>value.replace(/\D/g,'');
  const format=(value,isDate)=>{const v=digits(value).slice(0,isDate?8:4);return isDate?v.slice(0,2)+(v.length>2?'/'+v.slice(2,4):'')+(v.length>4?'/'+v.slice(4):''):v.slice(0,2)+(v.length>2?':'+v.slice(2):'');};
  const validDate=value=>{
    const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value);if(!m)return false;
    const d=Number(m[1]),month=Number(m[2]),year=Number(m[3]);if(year<1)return false;
    const parsed=new Date(0);parsed.setUTCFullYear(year,month-1,d);parsed.setUTCHours(0,0,0,0);
    return parsed.getUTCFullYear()===year&&parsed.getUTCMonth()===month-1&&parsed.getUTCDate()===d;
  };
  const validTime=value=>/^(?:[01]\d|2[0-3]):[0-5]\d$/.test(value);
  const minutes=value=>Number(value.slice(0,2))*60+Number(value.slice(3));
  const update=()=>{
    date.setCustomValidity(date.value&&!validDate(date.value)?'Informe uma data real no formato DD/MM/AAAA.':'');
    [start,end].forEach(field=>field.setCustomValidity(field.value&&!validTime(field.value)?'Informe uma hora entre 00:00 e 23:59.':''));
    if(!validDate(date.value)||!validTime(start.value)||!validTime(end.value)){output.textContent='Informe a data e as duas horas para calcular a duração.';return;}
    const duration=minutes(end.value)-minutes(start.value)+(next.checked?1440:0);
    if(duration<=0){end.setCustomValidity('O fim deve ser posterior ao início. Marque “Termina no dia seguinte” somente se houve virada de dia.');output.textContent='Intervalo inválido: confirme os horários e a opção de dia seguinte.';return;}
    output.textContent='Duração: '+(duration/60).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})+' h ('+Math.floor(duration/60)+'h '+duration%60+'min).';
  };
  const mask=(field,position=field.selectionStart)=>{
    const before=digits(field.value.slice(0,position??field.value.length)).length;
    field.value=format(field.value,field===date);
    let caret=0,count=0;while(caret<field.value.length&&count<before){if(/\d/.test(field.value[caret]))count++;caret++;}
    field.setSelectionRange(caret,caret);update();
  };
  [date,start,end].forEach(field=>{
    field.addEventListener('input',()=>mask(field));field.addEventListener('blur',()=>mask(field));
    field.addEventListener('paste',event=>{
      if(!event.clipboardData)return;
      event.preventDefault();const from=field.selectionStart??0,to=field.selectionEnd??field.value.length,text=event.clipboardData.getData('text');
      field.value=field.value.slice(0,from)+text+field.value.slice(to);mask(field,from+text.length);
    });
  });
  next.addEventListener('change',update);update();
})();
