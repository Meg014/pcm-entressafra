// Pure JavaScript DOM simulation; no database or network access.
function testUserMenu(source) {
  const assert=(value,message)=>{if(!value)throw new Error(message);};
  const listeners={};
  const document={activeElement:null,getElementById:id=>id==='pcm-user-menu'?menu:toggle,addEventListener:(name,fn)=>(listeners[name] ||= []).push(fn)};
  class Element {
    constructor(link=false) { this.handlers={}; this.attributes={}; this.link=link; }
    addEventListener(name,fn) { (this.handlers[name] ||= []).push(fn); }
    emit(name,event={}) { for(const fn of this.handlers[name]||[]) fn({preventDefault(){},...event}); }
    setAttribute(name,value) { this.attributes[name]=value; }
    focus() { document.activeElement=this; }
    closest() { return this.link?this:null; }
  }
  const menu=new Element(),toggle=new Element(),outside=new Element(),options=[new Element(true),new Element(true),new Element(true)];
  let open=false;
  Object.defineProperty(menu,'open',{get:()=>open,set:value=>{open=value;menu.emit('toggle');}});
  menu.contains=target=>target===menu||target===toggle||options.includes(target);
  menu.querySelectorAll=()=>options;
  const globalEvent=(name,event)=>{for(const fn of listeners[name]||[])fn({preventDefault(){},...event});};
  const nativeClickToggle=()=>{toggle.focus();menu.open=!menu.open;menu.emit('click',{target:toggle});globalEvent('click',{target:toggle});};
  new Function('document',source)(document);
  assert(!menu.open,'initially closed');
  nativeClickToggle(); assert(menu.open&&toggle.attributes['aria-expanded']==='true','click/touch opens');
  nativeClickToggle(); assert(!menu.open,'second click closes');
  nativeClickToggle();globalEvent('click',{target:outside});assert(!menu.open,'outside click closes');
  toggle.focus();menu.emit('keydown',{key:'ArrowDown'});assert(menu.open&&document.activeElement===options[0],'down opens and focuses first');
  menu.emit('keydown',{key:'ArrowDown'});assert(document.activeElement===options[1],'down moves');
  menu.emit('keydown',{key:'End'});assert(document.activeElement===options[2],'end moves to last');
  menu.emit('keydown',{key:'ArrowDown'});assert(document.activeElement===options[0],'wrap around');
  menu.emit('keydown',{key:'ArrowUp'});assert(document.activeElement===options[2],'up moves');
  menu.emit('keydown',{key:'Home'});assert(document.activeElement===options[0],'home moves to first');
  globalEvent('keydown',{key:'Escape'});assert(!menu.open&&document.activeElement===toggle,'escape closes and restores focus');
  nativeClickToggle();menu.emit('click',{target:options[0]});assert(!menu.open,'choosing option closes');
  nativeClickToggle();menu.emit('focusout',{relatedTarget:outside});assert(!menu.open,'tabbing outside closes');
  nativeClickToggle();menu.emit('click',{target:options[2]});assert(!menu.open,'logout button closes without intercepting submission');
  return 'Menu: clique, fechamento externo, Esc, setas, Home/End, foco, escolha e saída: OK (DOM simulado).';
}
