'use strict';
(() => {
  const form=document.getElementById('apontamento-form');if(!form)return;
  const date=form.elements.data_servico,start=form.elements.hora_inicio_manha,end=form.elements.hora_fim_manha,afternoonStart=form.elements.hora_inicio_tarde,afternoonEnd=form.elements.hora_fim_tarde;
  const output=document.getElementById('horas-calculadas');
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
   const times=[start,end,afternoonStart,afternoonEnd];
   times.forEach(f=>f.setCustomValidity(f.value&&!validTime(f.value)?'Informe uma hora válida entre 00:00 e 23:59.':''));
   let duration=0, invalid=false;
   for(const [from,to] of [[start,end],[afternoonStart,afternoonEnd]]) {
    if(!from.value&&!to.value)continue;
    if(!from.value||!to.value){(!from.value?from:to).setCustomValidity('Preencha início e fim do período.');invalid=true;continue;}
    if(!validTime(from.value)||!validTime(to.value)){invalid=true;continue;}
    const diff=minutes(to.value)-minutes(from.value);
    if(diff<=0){to.setCustomValidity('O fim deve ser posterior ao início, na mesma data.');invalid=true;}else duration+=diff;
   }
   if(times.every(f=>!f.value)){start.setCustomValidity('Preencha pelo menos um período completo.');invalid=true;}
   if(validTime(end.value)&&validTime(afternoonStart.value)&&minutes(end.value)>minutes(afternoonStart.value)){
    afternoonStart.setCustomValidity('Os períodos devem estar em ordem e sem sobreposição.');invalid=true;
   }
   const text=invalid?'':'Duração: '+Math.floor(duration/60)+'h '+duration%60+'min';
    output.textContent=text;
  };
  const mask=(field,position=field.selectionStart)=>{
    const before=digits(field.value.slice(0,position??field.value.length)).length;
    field.value=format(field.value,field===date);
    let caret=0,count=0;while(caret<field.value.length&&count<before){if(/\d/.test(field.value[caret]))count++;caret++;}
    field.setSelectionRange(caret,caret);update();
  };
  [date,start,end,afternoonStart,afternoonEnd].forEach(field=>{
    field.addEventListener('input',()=>mask(field));field.addEventListener('blur',()=>mask(field));
    field.addEventListener('paste',event=>{
      if(!event.clipboardData)return;
      event.preventDefault();const from=field.selectionStart??0,to=field.selectionEnd??field.value.length,text=event.clipboardData.getData('text');
      field.value=field.value.slice(0,from)+text+field.value.slice(to);mask(field,from+text.length);
    });
  });
  update();
})();
