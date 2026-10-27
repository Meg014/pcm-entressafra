function testEntryPeriodsBatch(source) {
 const field=()=>({value:'',events:{},dataset:{mask:'time'},selectionStart:0,validity:'',addEventListener(n,f){this.events[n]=f;},setCustomValidity(v){this.validity=v;},setSelectionRange(){}});
 const fields=Array.from({length:5},field);fields[0].dataset.mask='date';
 const output={textContent:''},button={addEventListener(){}},row={querySelectorAll:()=>fields,querySelector:s=>s==='.entry-duration'?output:s==='.entry-number span'?{}:button};
 const lines={children:[row]},form={addEventListener(){}},order={hidden:true,textContent:'',dataset:{order:'null'}};
 const document={getElementById:id=>({'batch-form':form,'entry-order-status':order,'entry-lines':lines,'os-autocomplete':button,'entry-count':{},'add-entry':button}[id])};
 new Function('document','window',source)(document,{initEmployeeAutocomplete(){},initServiceAutocomplete(){}});
 let assertions=0;const check=(v,m)=>{assertions++;if(!v)throw Error(m);};
 const set=values=>values.forEach((v,i)=>{fields[i].value=v;fields[i].selectionStart=v.length;fields[i].events.input();});
 set(['27102026','','','1200','1400']);check(output.textContent==='Duração: 2h 0min'&&fields.every(f=>!f.validity),'Somente tarde');
 set(['27102026','0700','1100','','']);check(output.textContent==='Duração: 4h 0min'&&fields.every(f=>!f.validity),'Somente manhã');
 set(['27102026','0700','1100','1200','1700']);check(output.textContent==='Duração: 9h 0min','Dois períodos');
 set(['27102026','','','','']);check(!!fields[1].validity,'Períodos vazios');
 set(['27102026','','','1200','']);check(!!fields[4].validity,'Fim obrigatório no período');
 set(['27102026','1100','0700','','']);check(!!fields[2].validity,'Invertido');
 set(['27102026','0700','1300','1200','1700']);check(!!fields[3].validity,'Sobreposição');
 set(['27102026','','','2300','0200']);check(!!fields[4].validity,'Meia-noite');
 return {status:'OK',assertions};
}
