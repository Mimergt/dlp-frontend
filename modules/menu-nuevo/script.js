/* Menú nuevo: barra fija con scrollspy, desplazamiento por categoría y búsqueda. Sin librerías. */
(function () {
  'use strict';
  var root = document.getElementById('dlpm');
  if (!root) return;
  var nav = document.getElementById('dlpm-nav'), chipsBox = nav.querySelector('.dlpm-chips');
  var chips = [].slice.call(nav.querySelectorAll('.dlpm-chip')), secs = [].slice.call(root.querySelectorAll('.dlpm-sec'));
  var sBtn = nav.querySelector('.dlpm-sbtn'), sBox = nav.querySelector('.dlpm-search'), q = nav.querySelector('.dlpm-q'), qx = nav.querySelector('.dlpm-qx'), none = root.querySelector('.dlpm-none');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var lock = 0, ticking = false; // lock: mientras se desplaza por un clic, el scrollspy no cambia la píldora

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
  function offset() { return topOffset() + nav.offsetHeight + 8; }

  function activar(slug) {
    chips.forEach(function (c) {
      var on = c.getAttribute('data-s') === slug;
      if (on === c.classList.contains('on')) return;
      c.classList.toggle('on', on);
      c.setAttribute('aria-selected', on ? 'true' : 'false');
      if (on) { // deja la píldora activa centrada en la barra, sin mover la página
        var x = c.offsetLeft - (chipsBox.clientWidth - c.offsetWidth) / 2;
        if (chipsBox.scrollTo) chipsBox.scrollTo({ left: x, behavior: reduce ? 'auto' : 'smooth' }); else chipsBox.scrollLeft = x;
      }
    });
  }

  function espiar() {
    ticking = false;
    if (lock) return;
    var y = offset() + Math.round(window.innerHeight * 0.22), cur = null; // cambia cuando el título de la categoría pasa del primer quinto de la pantalla
    for (var i = 0; i < secs.length; i++) {
      if (secs[i].hidden) continue;
      if (secs[i].getBoundingClientRect().top <= y) cur = secs[i]; else break;
    }
    if (!cur) cur = secs.filter(function (s) { return !s.hidden; })[0];
    if (cur) activar(cur.getAttribute('data-s'));
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(espiar); } }, { passive: true });
  window.addEventListener('resize', function () { setTop(); espiar(); });

  // Posición ideal del título de la sección: justo bajo la barra.
  function ideal(s) { return Math.max(0, Math.round(s.getBoundingClientRect().top + window.pageYOffset - offset() + 16)); }

  function ir(slug, suave) {
    var s = root.querySelector('.dlpm-sec[data-s="' + slug + '"]');
    if (!s || s.hidden) return;
    activar(slug);
    var y0 = ideal(s), anim = suave && !reduce && Math.abs(y0 - window.pageYOffset) < window.innerHeight * 1.6, fin = false; // lejos: salto directo (un scroll suave de miles de px es lento)
    lock = 1;
    window.scrollTo({ top: y0, behavior: anim ? 'smooth' : 'auto' });
    // Al terminar el desplazamiento se corrige cualquier diferencia (imágenes que cargaron, barra que cambió de alto) y se vuelve a espiar.
    function asentar() {
      if (fin) return;
      fin = true;
      window.removeEventListener('scrollend', asentar);
      var y = ideal(s);
      if (Math.abs(y - window.pageYOffset) > 2) window.scrollTo({ top: y, behavior: 'auto' });
      setTimeout(function () { lock = 0; espiar(); }, 80);
    }
    if (anim && 'onscrollend' in window) { window.addEventListener('scrollend', asentar); setTimeout(asentar, 1800); }
    else setTimeout(asentar, anim ? 900 : 60);
  }
  chips.forEach(function (c) {
    c.addEventListener('click', function (e) {
      e.preventDefault();
      if (q.value) { q.value = ''; filtrar(); }
      ir(c.getAttribute('data-s'), true);
    });
  });

  // ---- Búsqueda ------------------------------------------------------------------------------------------
  function plano(t) { return String(t || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); }
  function filtrar() {
    var w = plano(q.value).split(/\s+/).filter(Boolean), total = 0;
    qx.hidden = !q.value;
    secs.forEach(function (s) {
      var vis = 0;
      [].slice.call(s.querySelectorAll('li.product')).forEach(function (li) {
        var q2 = li.getAttribute('data-q') || '', ok = w.every(function (p) { return q2.indexOf(p) > -1; });
        li.hidden = !ok; if (ok) vis++;
      });
      s.hidden = vis === 0; total += vis;
      var c = chips.filter(function (x) { return x.getAttribute('data-s') === s.getAttribute('data-s'); })[0];
      if (c) c.classList.toggle('vacio', vis === 0);
    });
    none.hidden = total > 0 || !w.length;
    if (!w.length) chips.forEach(function (c) { c.classList.remove('vacio'); });
    espiar();
  }
  function abrirBusqueda(abrir) {
    sBox.hidden = !abrir; sBtn.setAttribute('aria-expanded', abrir ? 'true' : 'false');
    if (abrir) q.focus(); else if (q.value) { q.value = ''; filtrar(); }
  }
  sBtn.addEventListener('click', function () { abrirBusqueda(sBox.hidden); });
  q.addEventListener('input', filtrar);
  q.addEventListener('keydown', function (e) { if (e.key === 'Escape') abrirBusqueda(false); if (e.key === 'Enter') q.blur(); });
  qx.addEventListener('click', function () { q.value = ''; filtrar(); q.focus(); });

  // ---- Enlaces #categoria-<slug> (desde el Home o la barra inferior) ---------------------------------------
  function desdeHash() {
    var m = /^#categoria-([\w-]+)/.exec(location.hash);
    if (m) ir(m[1], false);
  }
  window.addEventListener('hashchange', desdeHash);

  setTop();
  espiar();
  if (location.hash) { desdeHash(); setTimeout(desdeHash, 450); }
})();
