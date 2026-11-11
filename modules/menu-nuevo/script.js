/* Menú nuevo: categorías al instante (solo se ve la activa), barra fija, búsqueda en todo el menú y gesto de deslizar. Sin librerías. */
(function () {
  'use strict';
  var root = document.getElementById('dlpm');
  if (!root) return;
  var nav = document.getElementById('dlpm-nav'), chipsBox = nav.querySelector('.dlpm-chips'), body = root.querySelector('.dlpm-body');
  var chips = [].slice.call(nav.querySelectorAll('.dlpm-chip')), secs = [].slice.call(root.querySelectorAll('.dlpm-sec'));
  var sBtn = nav.querySelector('.dlpm-sbtn'), sBox = nav.querySelector('.dlpm-search'), q = nav.querySelector('.dlpm-q'), qx = nav.querySelector('.dlpm-qx'), none = root.querySelector('.dlpm-none');
  var slugs = chips.map(function (c) { return c.getAttribute('data-s'); });
  var activa = slugs[0], reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Alto de lo que el tema deja fijo arriba (header de Divi, si queda fijo): la barra se pega justo debajo.
  function topOffset() {
    var max = 0;
    [].slice.call(document.querySelectorAll('header, #main-header, #top-header, .et-l--header, .et-fixed-header')).forEach(function (el) {
      var cs = getComputedStyle(el);
      if ((cs.position === 'fixed' || cs.position === 'sticky') && cs.display !== 'none') {
        var r = el.getBoundingClientRect();
        if (r.top <= 1 && r.bottom > max && r.height < 200) max = r.bottom;
      }
    });
    return Math.round(max);
  }
  function setTop() { root.style.setProperty('--dlpm-top', topOffset() + 'px'); }

  // ---- Cambio de categoría ------------------------------------------------------------------------------
  function pintar(slug) {
    chips.forEach(function (c) {
      var on = c.getAttribute('data-s') === slug;
      c.classList.toggle('on', on); c.setAttribute('aria-selected', on ? 'true' : 'false');
      if (on) { // centra la píldora activa en la barra sin mover la página
        var x = c.offsetLeft - (chipsBox.clientWidth - c.offsetWidth) / 2;
        if (chipsBox.scrollTo) chipsBox.scrollTo({ left: x, behavior: reduce ? 'auto' : 'smooth' }); else chipsBox.scrollLeft = x;
      }
    });
  }
  // Si la página está más abajo de la barra, vuelve al inicio de los productos para no dejar al cliente a mitad de la lista.
  function alInicio() {
    var y = body.getBoundingClientRect().top + window.pageYOffset - nav.offsetHeight - topOffset() - 6;
    if (window.pageYOffset > y) window.scrollTo({ top: Math.max(0, y), behavior: 'auto' });
  }
  function mostrar(slug, opts) {
    opts = opts || {};
    if (slugs.indexOf(slug) < 0) return;
    activa = slug;
    if (q.value) { q.value = ''; filtrar(); }
    secs.forEach(function (s) { s.hidden = s.getAttribute('data-s') !== slug; });
    pintar(slug);
    if (!opts.sinScroll) alInicio();
  }
  chips.forEach(function (a) {
    a.addEventListener('click', function (e) { e.preventDefault(); mostrar(a.getAttribute('data-s')); });
  });

  // ---- Búsqueda: resultados al instante en todas las categorías -------------------------------------------
  function plano(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }
  function filtrar() {
    var w = plano(q.value).split(/\s+/).filter(Boolean), total = 0;
    qx.hidden = !q.value;
    root.classList.toggle('buscando', w.length > 0);
    secs.forEach(function (s) {
      var vis = 0;
      [].slice.call(s.querySelectorAll('li.product')).forEach(function (li) {
        var t = li.getAttribute('data-q') || '', ok = !w.length || w.every(function (p) { return t.indexOf(p) > -1; });
        li.hidden = !ok; if (ok) vis++;
      });
      s.hidden = w.length ? vis === 0 : s.getAttribute('data-s') !== activa;
      total += w.length ? vis : 0;
    });
    none.hidden = total > 0 || !w.length;
  }
  function abrirBusqueda(abrir) {
    sBox.hidden = !abrir; sBtn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    if (abrir) q.focus(); else if (q.value) { q.value = ''; filtrar(); }
  }
  sBtn.addEventListener('click', function () { abrirBusqueda(sBox.hidden); });
  q.addEventListener('input', function () { filtrar(); if (q.value && window.pageYOffset > 0) alInicio(); });
  q.addEventListener('keydown', function (e) { if (e.key === 'Escape') abrirBusqueda(false); if (e.key === 'Enter') q.blur(); });
  qx.addEventListener('click', function () { q.value = ''; filtrar(); q.focus(); });

  // ---- Deslizar sobre los productos cambia de categoría (celular) -------------------------------------------
  var tx = 0, ty = 0, tt = 0, tOn = false;
  body.addEventListener('touchstart', function (e) {
    tOn = e.touches.length === 1 && !root.classList.contains('buscando');
    if (tOn) { tx = e.touches[0].clientX; ty = e.touches[0].clientY; tt = Date.now(); }
  }, { passive: true });
  body.addEventListener('touchend', function (e) {
    if (!tOn) return; tOn = false;
    var t = e.changedTouches[0], dx = t.clientX - tx, dy = t.clientY - ty;
    if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.8 || Date.now() - tt > 600) return;
    var i = slugs.indexOf(activa) + (dx < 0 ? 1 : -1);
    if (i >= 0 && i < slugs.length) mostrar(slugs[i]);
  }, { passive: true });

  // ---- Enlaces #categoria-<slug> (desde el Home o la barra inferior) ---------------------------------------
  function desdeHash() {
    var m = /^#categoria-([\w-]+)/.exec(location.hash);
    if (m) mostrar(m[1], { sinScroll: true });
  }
  window.addEventListener('hashchange', desdeHash);
  window.addEventListener('resize', setTop);

  // ---- Página de prueba: cambiar cómo se resalta la categoría actual al vuelo (?resalte=…) ------------------------
  var pr = root.querySelector('.dlpm-prueba');
  if (pr && root.getAttribute('data-prueba')) {
    pr.hidden = false;
    var bs = [].slice.call(pr.querySelectorAll('button'));
    var ponerResalte = function (b, guardar) {
      root.setAttribute('data-resalte', b);
      bs.forEach(function (x) { x.classList.toggle('on', x.getAttribute('data-b') === b); });
      if (guardar && window.history.replaceState) { var u = new URL(location.href); u.searchParams.set('resalte', b); history.replaceState(null, '', u.pathname + u.search + location.hash); }
    };
    var p0 = new URL(location.href).searchParams.get('resalte');
    bs.forEach(function (x) { x.addEventListener('click', function () { ponerResalte(x.getAttribute('data-b'), true); }); });
    ponerResalte(p0 && bs.some(function (x) { return x.getAttribute('data-b') === p0; }) ? p0 : root.getAttribute('data-resalte'), false);
  }

  setTop();
  pintar(activa);
  if (location.hash) desdeHash();
})();
