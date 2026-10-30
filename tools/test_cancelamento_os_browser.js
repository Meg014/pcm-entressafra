(() => {
    const results = [];
    const check = (value, message) => { if (!value) throw Error(message); results.push(message); };
    try {
        const dialog = document.getElementById('cancel-os-dialog');
        const form = document.getElementById('cancel-os-form');
        check(dialog.open && dialog.matches(':modal'), 'Confirmação abre em janela modal');
        check(dialog.textContent.includes('11 apontamentos') && dialog.textContent.includes('12 insumos'), 'Contagem abrange todas as páginas');
        check(dialog.textContent.includes('Descrição da O.S.'), 'Descrição e O.S. presentes');
        check(form.method === 'post' && form.action.includes('/excluir-registros'), 'Cancelamento usa POST específico');
        check(!!form.elements._csrfToken.value && form.elements.confirmacao.value.length === 64, 'Proteções CSRF e confirmação presentes');
        check(!form.checkValidity() && form.elements.motivo.required, 'Justificativa obrigatória');
        check(!document.querySelector('form form'), 'Formulários independentes');
        const cancel = dialog.querySelector('[data-cancel-os-return]');
        check(cancel.textContent === 'Cancelar' && !cancel.href.includes('excluir_os='), 'Cancelar fecha sem executar exclusão');
        check(cancel.href.includes('pagina=3') && cancel.href.includes('evolucao=concluidas'), 'Retorno preserva filtros e página');
        const confirm = form.querySelector('button[type=submit]');
        check(confirm.textContent === 'Confirmar exclusão' && confirm.name === 'confirmar' && confirm.value === '1', 'Confirmação explícita no botão');
        form.elements.motivo.value = 'Teste no navegador';
        let submitted = false;
        form.addEventListener('submit', event => {
            event.preventDefault();
            submitted = new FormData(form, event.submitter).get('confirmar') === '1';
        });
        form.requestSubmit(confirm);
        check(submitted, 'Envio autorizado inclui a confirmação');
        check(document.querySelectorAll('thead th').length === 9, 'Layout da tabela preservado');
        const bounds = dialog.getBoundingClientRect();
        check(bounds.left >= 0 && bounds.right <= window.innerWidth && bounds.bottom <= window.innerHeight, 'Modal cabe na tela');
        check(document.documentElement.scrollWidth <= window.innerWidth, 'Sem overflow horizontal');
        check(window.errors.length === 0, 'Sem erros JavaScript');
    } catch (error) { results.push('FAIL: ' + error.message); }
    const output = document.createElement('pre');
    output.id = 'browser-test-results';
    output.textContent = JSON.stringify({width: window.innerWidth, results, errors: window.errors});
    document.body.append(output);
})();
