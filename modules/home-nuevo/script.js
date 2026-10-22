/* Home nuevo: puntos y flechas de los slides, cambio automático del banner, filas de «más pedidos» que abren la vista
   rápida y enlaces #categoria-<slug> hacia las pestañas del menú. */
(function () {
  'use strict';

  // Slides infinitos y automáticos. Cada uno avanza a su ritmo (el banner es el más rápido). Se duplican los elementos
  // a cada lado y, cuando el scroll queda quieto, se salta de vuelta al conjunto original sin que se note.
  var cfg = (window.dlpFE && window.dlpFE['home-nuevo']) || {};
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var mqMovil = window.matchMedia('(max-width: 767px)');

  function slider(box) {
    var track = box.querySelector('.dlph-track');
    if (!track) return;
    var dots = [].slice.call(box.querySelectorAll('.dlph-dots i'));
    var prev = box.querySelector('.dlph-prev'), next = box.querySelector('.dlph-next');
    var isBanner = box.classList.contains('is-banner'), isCats = box.classList.contains('is-cats'), isCards = box.classList.contains('is-cards');
    var secs = parseFloat(isBanner ? cfg.autoplay : isCards ? cfg.autoplay_promos : cfg.autoplay_cats) || 0;
    var parent = isCards ? track.querySelector('ul') : track;
    var orig = [], afters = [], S = 0, setW = 0, step = 0, looping = false, idle = 0, paused = false, timer = 0;

    function nonClones() { return [].slice.call(parent.children).filter(function (e) { return !e.classList.contains('dlph-clone') && !e.classList.contains('dlph-ct--menu'); }); }
    function clearClones() { [].slice.call(parent.querySelectorAll('.dlph-clone')).forEach(function (c) { c.remove(); }); }

    function setup() {
      clearClones();
      orig = nonClones();
      afters = [];
      looping = false;
      var scrollable = track.scrollWidth > track.clientWidth + 4;
      if (isCats && !mqMovil.matches) scrollable = false;
      if (orig.length < 2 || (!scrollable && !isBanner)) { update(); return; }
      var first = parent.firstElementChild;
      orig.slice().reverse().forEach(function (e) { var c = e.cloneNode(true); c.classList.add('dlph-clone'); c.setAttribute('aria-hidden', 'true'); c.setAttribute('tabindex', '-1'); parent.insertBefore(c, first); first = c; });
      orig.forEach(function (e) { var c = e.cloneNode(true); c.classList.add('dlph-clone'); c.setAttribute('aria-hidden', 'true'); c.setAttribute('tabindex', '-1'); parent.appendChild(c); afters.push(c); });
      looping = true;
      measure();
      track.style.scrollBehavior = 'auto';
      track.scrollLeft = S;
      track.style.scrollBehavior = '';
      update();
    }

    function measure() {
      var firstOrig = orig[0], firstAfter = afters[0];
      if (!firstOrig || !firstAfter) return;
      S = firstOrig.offsetLeft - (parseFloat(getComputedStyle(track).scrollPaddingLeft) || 0);
      setW = firstAfter.offsetLeft - firstOrig.offsetLeft;
      step = orig.length > 1 ? orig[1].offsetLeft - orig[0].offsetLeft : track.clientWidth;
    }

    function normalize() {
      if (!looping || !setW) return;
      var pos = track.scrollLeft - S;
      var np = pos;
      if (pos < -2) np = pos + setW;
      else if (pos >= setW - 2) np = pos - setW;
      if (np !== pos) {
        track.style.scrollBehavior = 'auto';
        track.scrollLeft = S + np;
        track.style.scrollBehavior = '';
      }
    }

    function update() {
      if (!dots.length) return;
      var n = Math.max(1, orig.length), i = 0;
      if (looping && step) { i = Math.round((track.scrollLeft - S) / step); i = ((i % n) + n) % n; }
      else if (step || track.clientWidth) { var st = (orig[1] && orig[0]) ? orig[1].offsetLeft - orig[0].offsetLeft : track.clientWidth; i = Math.min(n - 1, Math.round(track.scrollLeft / Math.max(1, st))); }
      dots.forEach(function (d, k) { d.classList.toggle('on', k === i); });
    }

    function go(dir) {
      if (!looping) {
        var st = (orig[1] && orig[0]) ? orig[1].offsetLeft - orig[0].offsetLeft : track.clientWidth;
        track.scrollBy({ left: dir * st, behavior: 'smooth' });
        return;
      }
      normalize();
      track.scrollBy({ left: dir * step, behavior: 'smooth' });
    }

    if (prev) prev.addEventListener('click', function () { go(-1); restart(); });
    if (next) next.addEventListener('click', function () { go(1); restart(); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { if (looping) { normalize(); track.scrollTo({ left: S + k * step, behavior: 'smooth' }); } else { track.scrollTo({ left: k * (orig[1] ? orig[1].offsetLeft - orig[0].offsetLeft : track.clientWidth), behavior: 'smooth' }); } restart(); }); });
    track.addEventListener('scroll', function () {
      window.requestAnimationFrame(update);
      clearTimeout(idle);
      idle = setTimeout(normalize, 110);
    }, { passive: true });

    function tick() { if (!paused && !document.hidden && secs > 0 && !reduce) go(1); }
    function restart() { clearInterval(timer); if (secs > 0 && !reduce) timer = setInterval(tick, secs * 1000); }
    ['pointerenter', 'focusin'].forEach(function (ev) { box.addEventListener(ev, function () { paused = true; }); });
    ['pointerleave', 'focusout'].forEach(function (ev) { box.addEventListener(ev, function () { paused = false; }); });
    box.addEventListener('touchstart', function () { paused = true; }, { passive: true });
    box.addEventListener('touchend', function () { setTimeout(function () { paused = false; }, 3000); }, { passive: true });

    var lastMovil = mqMovil.matches, rz = 0;
    window.addEventListener('resize', function () {
      clearTimeout(rz);
      rz = setTimeout(function () { if (isCats && mqMovil.matches !== lastMovil) { lastMovil = mqMovil.matches; setup(); } else { measure(); } update(); }, 150);
    });
    window.addEventListener('load', function () { setup(); });
    setup();
    restart();
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
