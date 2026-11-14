/*
 * Splash / transición. Al cargar: la capa (ya visible) se quita cuando termina la carga y pasó el tiempo mínimo
 * (solo la primera vez de la sesión; después, rápido). Al tocar un enlace interno: la capa aparece y el navegador
 * carga la página nueva.
 */
(function () {
  'use strict';
  var el = document.querySelector('.dlpta');
  if (!el) return;
  var cfg = (window.dlpFE && window.dlpFE['splash-transicion']) || {};
  var first = true;
  try { first = !sessionStorage.getItem('dlpta'); sessionStorage.setItem('dlpta', '1'); } catch (e) {}
  var started = Date.now();
  var min = first ? (parseInt(cfg.min_ms, 10) || 700) : 250;

  function hide() { el.classList.remove('is-on'); }
  var quitado = false;
  function done() { if (quitado) return; quitado = true; setTimeout(hide, Math.max(0, min - (Date.now() - started))); }
  // Se quita cuando carga toda la página, o antes si la imagen principal del banner ya cargó (no hace falta esperar scripts de terceros).
  var hero = document.querySelector('.dlph-bimg');
  if (document.readyState === 'complete') done();
  else {
    window.addEventListener('load', done);
    if (hero) { if (hero.complete && hero.naturalWidth) done(); else hero.addEventListener('load', done); }
  }
  // Volver con "Atrás" (página en caché): sin capa.
  window.addEventListener('pageshow', function (e) { if (e.persisted) hide(); });

  if (cfg.transicion === false) return;
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    if (window.innerWidth > 767) return;
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
    if (a.closest('.xoo-wsc-modal, .dlpqv, #wpadminbar, li.product') || a.matches('.ajax_add_to_cart, .xoo-wsc-cart-trigger, .added_to_cart')) return;
    var u;
    try { u = new URL(a.href, location.href); } catch (err) { return; }
    if (u.origin !== location.origin || !/^https?:$/.test(u.protocol)) return;
    if (u.pathname === location.pathname && u.search === location.search) return; // ancla o misma página
    if (/\/wp-admin|wp-login|logout|add-to-cart|\.(pdf|zip|jpe?g|png|gif|webp)$/i.test(u.pathname + u.search)) return;
    el.classList.add('is-on');
  });
})();
