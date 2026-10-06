/*
 * Menú inferior: el botón Carrito reutiliza el carrito lateral (Xootix) haciendo clic en su botón flotante
 * (oculto por CSS en celular), y copia la cantidad de su contador.
 */
(function () {
  'use strict';
  var nav = document.querySelector('.dlpmi');
  if (!nav) return;
  document.body.classList.add('dlpmi-on');
  var bd = nav.querySelector('.dlpmi-bd');

  function count() {
    var el = document.querySelector('.xoo-wsc-basket .xoo-wsc-items-count');
    if (!el) return;
    var n = parseInt(el.textContent, 10) || 0;
    bd.textContent = n;
    bd.hidden = n < 1;
  }

  nav.addEventListener('click', function (e) {
    var b = e.target.closest('[data-dlpmi]');
    if (!b) return;
    var k = b.getAttribute('data-dlpmi');
    if (k === 'cart') {
      e.preventDefault();
      var basket = document.querySelector('.xoo-wsc-basket');
      if (basket) basket.click(); else window.location.href = (window.wc_cart_params && wc_cart_params.cart_url) || '/carrito/';
    } else if (k === 'menu') {
      var tabs = document.querySelector('.navMenu_slide');
      if (tabs && document.body.classList.contains('home')) {
        e.preventDefault();
        window.scrollTo({ top: tabs.getBoundingClientRect().top + window.pageYOffset - 70, behavior: 'smooth' });
      }
    }
  });

  count();
  setInterval(count, 1000);
  if (window.jQuery) jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed wc_fragments_loaded', function () { setTimeout(count, 300); });
})();
