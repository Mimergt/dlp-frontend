/* Home nuevo: puntos y flechas de los slides, cambio automático del banner, filas de «más pedidos» que abren la vista
   rápida y enlaces #categoria-<slug> hacia las pestañas del menú. */
(function () {
  'use strict';

  function slider(box) {
    var track = box.querySelector('.dlph-track');
    var dots = [].slice.call(box.querySelectorAll('.dlph-dots i'));
    var prev = box.querySelector('.dlph-prev'), next = box.querySelector('.dlph-next');
    if (!track) return;
    function items() { return [].slice.call(track.querySelectorAll(':scope > a, :scope > ul > li, :scope > .dlph-slide')); }
    function step() {
      var it = items()[0];
      if (!it) return track.clientWidth;
      var gap = parseFloat(getComputedStyle(it.parentNode).columnGap) || parseFloat(getComputedStyle(track).columnGap) || 0;
      return it.getBoundingClientRect().width + gap;
    }
    function update() {
      var s = step(), i = Math.round(track.scrollLeft / Math.max(1, s));
      var max = Math.max(0, Math.round((track.scrollWidth - track.clientWidth) / Math.max(1, s)));
      i = Math.min(i, max);
      dots.forEach(function (d, k) { d.classList.toggle('on', k === Math.min(i, dots.length - 1)); });
      if (prev) prev.disabled = track.scrollLeft <= 2;
      if (next) next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
      var cont = box.querySelector('.dlph-ind');
      if (cont) cont.style.visibility = track.scrollWidth - track.clientWidth > 4 || box.classList.contains('is-banner') ? '' : 'hidden';
    }
    function go(dir) {
      var atEnd = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
      if (dir > 0 && atEnd && box.classList.contains('is-banner')) { track.scrollTo({ left: 0, behavior: 'smooth' }); return; }
      track.scrollBy({ left: dir * step(), behavior: 'smooth' });
    }
    if (prev) prev.addEventListener('click', function () { go(-1); });
    if (next) next.addEventListener('click', function () { go(1); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { track.scrollTo({ left: k * step(), behavior: 'smooth' }); }); });
    track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
    window.addEventListener('resize', update);
    update();

    var cfg = (window.dlpFE && window.dlpFE['home-nuevo']) || {};
    var secs = parseFloat(cfg.autoplay) || 0;
    if (box.classList.contains('is-banner') && secs > 0) {
      var paused = false;
      ['pointerdown', 'touchstart', 'mouseenter'].forEach(function (ev) { box.addEventListener(ev, function () { paused = true; }, { passive: true }); });
      box.addEventListener('mouseleave', function () { paused = false; });
      setInterval(function () { if (!paused && !document.hidden) go(1); }, secs * 1000);
    }
  }
  [].forEach.call(document.querySelectorAll('.dlph-slider'), slider);

  // Más pedidos: abre la vista rápida (ya escucha el hash #producto-<slug>)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('.dlph-li');
    if (!a) return;
    var inQuick = !!window.jQuery && !!document.querySelector('.dlpqv, [data-dlpqv-close]') || (window.dlpFE && window.dlpFE['home-quickview']);
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.which > 1) return;
    if (inQuick) { e.preventDefault(); window.location.hash = '#producto-' + a.getAttribute('data-slug'); }
  });

  // En /menu/: #categoria-<slug> activa la pestaña de esa categoría
  function norm(s) { return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''); }
  function irCategoria() {
    var m = /^#categoria-(.+)$/.exec(location.hash);
    var slides = [].slice.call(document.querySelectorAll('.navMenu_slide .swiper-slide'));
    if (!m || !slides.length) return false;
    var want = decodeURIComponent(m[1]);
    var names = { 'combos': ['combos-de-res', 'combos'], 'opciones-pollo': ['combos-de-pollo', 'opciones-pollo'], 'hamburguesas': ['hamburguesas-individuales', 'hamburguesas'], 'do-it-yourself': ['para-hacer-en-casa'] };
    var cands = [want].concat(names[want] || []);
    for (var i = 0; i < slides.length; i++) {
      var l = slides[i].querySelector('.navMenu_label');
      if (l && cands.indexOf(norm(l.textContent)) !== -1) { slides[i].click(); return true; }
    }
    return false;
  }
  if (/^#categoria-/.test(location.hash)) {
    var tries = 0, t = setInterval(function () { if (irCategoria() || ++tries > 20) clearInterval(t); }, 300);
  }
})();
