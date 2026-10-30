(() => {
    const dialog = document.getElementById('cancel-os-dialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    dialog.removeAttribute('open');
    dialog.showModal();
    dialog.addEventListener('cancel', event => {
        event.preventDefault();
        window.location.assign(dialog.querySelector('[data-cancel-os-return]').href);
    });
})();
