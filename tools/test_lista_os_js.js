function testListaOs(source){
    for(const paused of [false,true])for(const manualFirst of [false,true]){
        let submissions=0,handler,timer;const button={disabled:false};
        const form={dataset:{autoProcess:paused?'0':'1'},addEventListener:(type,fn)=>handler=fn,querySelectorAll:()=>[button],requestSubmit(){let cancelled=false;handler({preventDefault(){cancelled=true;}});if(!cancelled)submissions++;}};
        const doc={getElementById:()=>null,querySelector:()=>form};
        Function('document','window',source)(doc,{setTimeout:fn=>timer=fn});
        if(manualFirst)form.requestSubmit();
        if(timer)timer();
        if(!paused||manualFirst){form.requestSubmit();if(submissions!==1||!button.disabled)throw Error('Continuação duplicada');}
        else if(submissions!==0||timer)throw Error('Falha não pausou continuação');
    }
    return 'Lista: continuação automática, pausa após erro e duplo clique sem submissão duplicada';
}
