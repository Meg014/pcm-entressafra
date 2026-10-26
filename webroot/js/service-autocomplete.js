'use strict';
window.initServiceAutocomplete = root => {
 if (!root || root.dataset.initialized) return;
 root.dataset.initialized='1';
 const field=root.querySelector('textarea'),list=root.querySelector('[role="listbox"]');
 let timer,controller,version=0,active=-1,items=[];
 const close=()=>{list.hidden=true;field.setAttribute('aria-expanded','false');field.removeAttribute('aria-activedescendant');active=-1;};
 const cancel=()=>{clearTimeout(timer);controller?.abort();version++;close();};
 const select=i=>{if(!items[i])return;field.value=items[i].descricao;cancel();field.dispatchEvent(new Event('change',{bubbles:true}));field.focus();field.setSelectionRange(field.value.length,field.value.length);};
 const highlight=i=>{active=i;[...list.children].forEach((option,n)=>option.setAttribute('aria-selected',String(n===i)));field.setAttribute('aria-activedescendant',list.children[i].id);list.children[i].scrollIntoView({block:'nearest'});};
 field.addEventListener('input',()=>{
  cancel();const term=field.value.trim();if(term.length<2||term.length>100)return;
  const request=version;
  timer=setTimeout(async()=>{
   controller=new AbortController();
   try{
    const url=new URL(root.dataset.url,location.href);url.searchParams.set('q',term);
    const response=await fetch(url,{signal:controller.signal,headers:{Accept:'application/json'},credentials:'same-origin'});
    if(!response.ok)return;
    const data=await response.json();if(request!==version||!root.isConnected||document.activeElement!==field)return;
    items=(data.results||[]).filter(item=>typeof item.descricao==='string').slice(0,10);list.replaceChildren();
    items.forEach((item,i)=>{
     const option=document.createElement('div');option.id=list.id+'-'+i;option.setAttribute('role','option');option.setAttribute('aria-selected','false');option.textContent=item.descricao;
     option.addEventListener('pointerdown',event=>event.preventDefault());option.addEventListener('click',()=>select(i));list.append(option);
    });
    list.hidden=!items.length;field.setAttribute('aria-expanded',String(!!items.length));
   }catch(error){if(request===version)close();}
  },250);
 });
 field.addEventListener('keydown',event=>{
  if(event.key==='Escape'){cancel();return;}
  if(list.hidden||event.isComposing)return;
  if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();highlight((active+(event.key==='ArrowDown'?1:-1)+items.length)%items.length);}
  else if(event.key==='Enter'&&active>=0){event.preventDefault();select(active);}
 });
 field.addEventListener('blur',cancel);
};
