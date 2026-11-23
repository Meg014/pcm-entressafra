'use strict';
(() => {
 const dialog=document.getElementById('insumo-edicao');if(!dialog)return;
 const form=document.getElementById('insumo-edicao-form');
 document.querySelectorAll('[data-edit-supply]').forEach(button=>button.addEventListener('click',()=>{
  const d=button.dataset;form.reset();form.action=d.url;
  dialog.querySelector('[data-edit-os]').textContent=d.os;
  dialog.querySelector('[data-edit-employee]').textContent=d.employee;
  dialog.querySelector('[data-edit-date]').textContent=d.date;
  document.getElementById('edit-material').value=d.material;
  document.getElementById('edit-quantity').value=d.quantity.replace('.',',');
  document.getElementById('edit-unit').value=d.unit;
  document.getElementById('edit-revision').value=d.revision;
  document.getElementById('edit-reason').value=d.unit?'':'Complementação da unidade de medida';
  const confirmed=d.confirmed==='1';
  document.getElementById('edit-confirmed').hidden=!confirmed;
  document.getElementById('edit-awareness').required=confirmed;
  dialog.showModal();
 }));
 document.getElementById('edit-cancel').addEventListener('click',()=>dialog.close());
})();
