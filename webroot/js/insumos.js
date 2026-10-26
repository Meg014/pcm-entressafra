'use strict';
(() => {
 const form=document.getElementById('insumos-form');if(!form)return;
 const lines=document.getElementById('supplies-lines');let serial=lines.children.length;
 const number=()=>{document.getElementById('supplies-count').value=lines.children.length;[...lines.children].forEach((row,i)=>row.querySelector('.entry-number span').textContent=i+1);};
 const init=row=>{
  window.initEmployeeAutocomplete(row.querySelector('[data-lookup]'));
  const date=row.querySelector('[data-supply-date]'),quantity=row.querySelector('[data-supply-quantity]');
  date.addEventListener('input',()=>{
   const value=date.value.replace(/\D/g,'').slice(0,8);
   date.value=value.slice(0,2)+(value.length>2?'/'+value.slice(2,4):'')+(value.length>4?'/'+value.slice(4):'');
   const m=/^(\d{2})\/(\d{2})\/(\d{4})$/.exec(date.value);let valid=false;
   if(m&&+m[3]>0){const d=new Date(0);d.setUTCFullYear(+m[3],+m[2]-1,+m[1]);valid=d.getUTCFullYear()===+m[3]&&d.getUTCMonth()===+m[2]-1&&d.getUTCDate()===+m[1];}
   date.setCustomValidity(date.value&&!valid?'Informe uma data válida.':'');
  });
  quantity.addEventListener('input',()=>quantity.setCustomValidity(quantity.value&&(!/^\d{1,14}(?:[.,]\d{1,4})?$/.test(quantity.value)||Number(quantity.value.replace(',','.'))<=0)?'Informe uma quantidade maior que zero, com até 4 casas decimais.':''));
  row.querySelector('.supplies-remove').addEventListener('click',()=>{row.remove();if(!lines.children.length)add();number();});
 };
 const add=()=>{const holder=document.createElement('div');holder.innerHTML=document.getElementById('supplies-template').innerHTML.replaceAll('__INDEX__',String(serial++));const row=holder.firstElementChild;lines.append(row);init(row);number();row.querySelector('input').focus();};
 [...lines.children].forEach(init);document.getElementById('supplies-add').addEventListener('click',add);
 form.addEventListener('submit',()=>[...lines.children].forEach((row,i)=>row.querySelectorAll('[name]').forEach(field=>field.name=field.name.replace(/^insumos\[[^\]]+\]/,'insumos['+i+']'))));
})();
