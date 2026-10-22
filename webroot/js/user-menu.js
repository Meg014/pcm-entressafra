(() => {
  const menu = document.getElementById('pcm-user-menu');
  if (!menu) return;
  const toggle = document.getElementById('pcm-user-toggle');
  const items = () => [...menu.querySelectorAll('a[href], button[type="submit"]')];
  const close = (restoreFocus = false) => {
    menu.open = false; toggle.setAttribute('aria-expanded','false');
    if (restoreFocus) toggle.focus();
  };
  menu.addEventListener('toggle',() => toggle.setAttribute('aria-expanded',String(menu.open)));
  document.addEventListener('click',event => { if (!menu.contains(event.target)) close(); });
  menu.addEventListener('click',event => { if (event.target.closest('a[href], button[type="submit"]')) close(); });
  document.addEventListener('keydown',event => {
    if (event.key === 'Escape' && menu.open) { event.preventDefault(); close(menu.contains(document.activeElement)); }
  });
  menu.addEventListener('focusout',event => { if (event.relatedTarget && !menu.contains(event.relatedTarget)) close(); });
  menu.addEventListener('keydown',event => {
    if (!['ArrowDown','ArrowUp','Home','End'].includes(event.key)) return;
    if (!menu.open && !['ArrowDown','ArrowUp'].includes(event.key)) return;
    const options = items(); if (!options.length) return;
    event.preventDefault();
    const current = options.indexOf(document.activeElement);
    menu.open = true; toggle.setAttribute('aria-expanded','true');
    const index = event.key === 'Home' ? 0 : event.key === 'End' ? options.length-1 :
      current < 0 ? (event.key === 'ArrowUp' ? options.length-1 : 0) :
      (current + (event.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
    options[index].focus();
  });
})();
