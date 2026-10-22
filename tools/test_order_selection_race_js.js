async function testOrderSelectionRace(source) {
    let assertions=0;const check=(ok,message)=>{assertions++;if(!ok)throw new Error(message);};
    class Element {
        constructor(){this.listeners={};this.children=[];this.dataset={};this.value='';this.hidden=true;this.textContent='';}
        addEventListener(name,fn){(this.listeners[name]??=[]).push(fn);}
        emit(name,event={}){for(const fn of this.listeners[name]??[])fn({preventDefault(){},...event});}
        setAttribute(){} removeAttribute(){} focus(){} setCustomValidity(){} reportValidity(){} scrollIntoView(){}
        replaceChildren(){this.children=[];}append(child){this.children.push(child);}contains(){return true;}querySelectorAll(){return this.children;}click(){this.emit('click');}
    }
    const elements={};const get=id=>elements[id]??=new Element();const root=get('os-autocomplete');root.dataset={identify:'/operacao/identificar',lookup:'/operacao/ordens'};
    const input=get('os-pesquisa');input.form=new Element();input.form.elements={_csrfToken:{value:'token-de-teste'}};
    const timers=new Map();let tid=0,rejectIdentity;
    const candidate={selecionavel:true,id:null,os_chave:'os-005300',numero:'005300',filial:'01',servico:'ENTRESSAFRA',setor:'Elétrica',caderno:'Sem caderno',situacao:'L'};
    const document={getElementById:get,createElement:()=>new Element(),addEventListener(){}};
    const fetch=async(target,options)=>options.method==='POST'?new Promise((resolve,reject)=>{rejectIdentity=reject;}):{ok:true,redirected:false,json:async()=>({results:[candidate],has_more:false})};
    new Function('document','window','fetch','setTimeout','clearTimeout','AbortController','URL',source)(document,{location:{origin:'http://localhost'}},fetch,fn=>{timers.set(++tid,fn);return tid;},id=>timers.delete(id),AbortController,URL);
    const flush=async()=>{for(const[id,fn]of[...timers]){timers.delete(id);await fn();}for(let i=0;i<8;i++)await Promise.resolve();};
    input.value='005300';input.emit('input');await flush();get('os-resultados').children[0].click();await flush();
    check(get('atividade-selecionada').value==='','Não seleciona ID antes de confirmar o TOTVS.');
    root.dataset.identify='';candidate.id='42';input.value='005301';input.emit('input');await flush();get('os-resultados').children[0].click();await flush();
    check(get('atividade-selecionada').value==='42','A nova seleção mantém seu ID confirmado.');const message=get('os-aviso').textContent;
    rejectIdentity(new Error('O.S. não encontrada'));await flush();
    check(get('os-aviso').textContent===message,'Erro atrasado de outra identificação não pode contradizer a O.S. selecionada.');
    check(!get('os-resumo').hidden,'Resumo confirmado continua visível.');
    return {status:'OK',assertions};
}
