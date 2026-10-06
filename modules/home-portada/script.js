/*
 * Home / Portada.
 *  1. Margen a los lados de las categorías en el estilo "curva" (para que se vea la curva del primer tab).
 *  2. Barra mini fija: al pasar de largo las categorías aparece arriba una versión compacta; desaparece al volver.
 * Ajustes del panel: window.dlpFE["home-portada"] (barra_fija, ...).
 */
(function () {
  'use strict';
  var cfg = (window.dlpFE && window.dlpFE['home-portada']) || {};
  var curva = /\bdlp-cat--curva\b/.test(document.body.className);

  // El carrusel de categorías lo inicializa otro plugin: se espera a que exista su instancia de Swiper.
  function whenReady(cb) {
    var tries = 0;
    (function tick() {
      var nav = document.querySelector('.navMenu_slide');
      if (nav && nav.swiper) return cb(nav);
      if (++tries < 80) setTimeout(tick, 100);
    })();
  }

  function mainSwiper() {
    var m = document.querySelector('.main_slider');
    return m && m.swiper;
  }

  function buildSubnav(nav) {
    var slides = [].slice.call(nav.querySelectorAll('.swiper-slide'));
    if (!slides.length) return;

    var bar = document.createElement('div');
    bar.className = 'dlp-subnav';
    bar.setAttribute('role', 'navigation');
    bar.setAttribute('aria-label', 'Categorías');
    var track = document.createElement('div');
    track.className = 'dlp-subnav__track';
    var tabs = slides.map(function (s, i) {
      var img = s.querySelector('img'), lab = s.querySelector('.navMenu_label');
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'dlp-subnav__tab';
      if (img) {
        var im = document.createElement('img');
        im.src = img.currentSrc || img.src;
        im.alt = '';
        b.appendChild(im);
      }
      var sp = document.createElement('span');
      sp.textContent = lab ? lab.textContent.trim() : '';
      b.appendChild(sp);
      b.addEventListener('click', function () {
        var m = mainSwiper();
        if (m) m.slideTo(i);
        else slides[i].click();
        var target = (document.querySelector('.main_slider') || nav);
        var top = target.getBoundingClientRect().top + window.pageYOffset - bar.offsetHeight - 8;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
      });
      track.appendChild(b);
      return b;
    });
    bar.appendChild(track);
    document.body.appendChild(bar);

    function syncActive() {
      var idx = slides.findIndex(function (s) { return s.classList.contains('swiper-slide-thumb-active'); });
      if (idx < 0) idx = nav.swiper ? nav.swiper.activeIndex : 0;
      tabs.forEach(function (t, i) { t.classList.toggle('is-active', i === idx); });
      var t = tabs[idx];
      if (t && bar.classList.contains('is-on')) {
        track.scrollTo({ left: t.offsetLeft - (track.clientWidth - t.offsetWidth) / 2, behavior: 'smooth' });
      }
    }
    syncActive();
    new MutationObserver(syncActive).observe(nav, { attributes: true, attributeFilter: ['class'], subtree: true });

    // Visible cuando las categorías originales quedaron por encima de la pantalla.
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (entries) {
        var e = entries[0];
        var on = !e.isIntersecting && e.boundingClientRect.top < 0;
        bar.classList.toggle('is-on', on);
        if (on) syncActive();
      }).observe(nav);
    }
  }

  // Margen lateral: idempotente, porque el plugin del carrusel puede reiniciar sus parámetros durante la carga.
  function applyPadding() {
    var nav = document.querySelector('.navMenu_slide');
    var sw = nav && nav.swiper;
    if (!sw) return;
    var pad = window.innerWidth >= 768 ? 28 : 16;
    if (sw.params.slidesOffsetBefore !== pad || sw.params.slidesOffsetAfter !== pad) {
      sw.params.slidesOffsetBefore = pad;
      sw.params.slidesOffsetAfter = pad;
      sw.update();
    }
  }

  whenReady(function (nav) {
    if (curva) {
      applyPadding();
      window.addEventListener('load', applyPadding);
      window.addEventListener('resize', applyPadding);
      [400, 1200, 2500].forEach(function (ms) { setTimeout(applyPadding, ms); });
    }
    if (cfg.barra_fija) buildSubnav(nav);
  });
})();
