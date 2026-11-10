'use strict';
// Browser harness: load before the production script in the companion HTML.
let refresh, requested, replaced = false;
const check = (condition, message) => { if (!condition) throw Error(message); };
const scroll = {scrollLeft: 125}, nextScroll = {scrollLeft: 0};
const next = {querySelector: () => nextScroll};
const current = {
 dataset: {}, querySelector: () => scroll,
 replaceWith(value) { check(value === next, 'Substituição do relatório'); replaced = true; }
};
const expectedUrl = location.href;
Object.defineProperty(document, 'hidden', {value: false});
document.querySelector = () => null;
document.getElementById = () => current;
window.setInterval = (callback, delay) => { check(delay === 30000, 'Intervalo de 30 segundos'); refresh = callback; };
window.fetch = async (href, options) => {
 requested = href; check(options.cache === 'no-store', 'Sem cache');
 return {ok: true, redirected: false, text: async () => '<filtered-report>'};
};
window.DOMParser = class {parseFromString() { return {getElementById: () => next}; }};
window.addEventListener('load', async () => {
 try {
  await refresh();
  check(requested === expectedUrl, 'URL preservada');
  check(requested.includes('corte=2026-11-09') && requested.includes('exibicao=automatica'), 'Filtros preservados');
  check(requested.includes('grupo=bombas'), 'Grupo da Mecânica preservado');
  check(replaced && nextScroll.scrollLeft === 125, 'Relatório e rolagem preservados');
  requested = undefined; current.dataset.simulation = 'true'; await refresh();
  check(requested === undefined, 'Simulação sem atualização');
  document.body.textContent = 'PASS: refresh 30000ms; setor, corte, exibição e rolagem preservados; simulação isolada.';
 } catch(error) { document.body.textContent = 'FAIL: ' + error.message; }
});
