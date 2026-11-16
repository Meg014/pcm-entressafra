(() => {
 const button=document.getElementById('porcentagens-exportar');
 if(!button)return;
 const error=document.getElementById('porcentagens-exportar-erro');
 button.addEventListener('click',async()=>{
  const sector=button.form.elements.area_id;
  error.hidden=true;
  if(!sector.value){sector.setCustomValidity('Selecione o setor para exportar.');sector.reportValidity();sector.setCustomValidity('');return;}
  const url=new URL(button.dataset.exportUrl,window.location.href);
  url.searchParams.set('area_id',sector.value);
  button.disabled=true;
  try{
   const response=await fetch(url.href,{credentials:'same-origin',cache:'no-store'});
   const type=(response.headers.get('Content-Type')||'').split(';')[0].trim();
   if(!response.ok||response.redirected||type!=='application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'){
    const message=type==='text/plain'?await response.text():'';
    throw Error(message||(response.status===403?'Acesso negado para exportar este setor/entressafra.':'Não foi possível baixar o Excel. Verifique sua sessão e tente novamente.'));
   }
   const blob=await response.blob();
   const disposition=response.headers.get('Content-Disposition')||'';
   const filename=/filename="?([^";]+)"?/i.exec(disposition)?.[1]||'porcentagens.xlsx';
   const downloadUrl=URL.createObjectURL(blob);
   const link=document.createElement('a');
   link.href=downloadUrl;link.download=filename;link.hidden=true;
   document.body.append(link);link.click();link.remove();
   setTimeout(()=>URL.revokeObjectURL(downloadUrl),60000);
  }catch(ex){error.textContent=ex.message||'Não foi possível baixar o Excel.';error.hidden=false;}
  finally{button.disabled=false;}
 });
})();
