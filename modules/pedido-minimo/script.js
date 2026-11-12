/* Pedido mínimo: mientras falte monto, «Pagar» del carrito lateral no avanza y lleva la vista al aviso. */
(function () {
  'use strict';
  function box() { return document.querySelector('.xoo-wsc-container .dlpmin[data-falta]'); }
  function falta() { var b = box(); return b ? parseFloat(b.getAttribute('data-falta')) || 0 : 0; }
  function marcar() {
    var p = document.querySelector('.xoo-wsc-ft-btn-checkout');
    if (p) p.classList.toggle('dlpmin-off', falta() > 0.0001);
  }
  document.addEventListener('click', function (e) {
    var p = e.target.closest && e.target.closest('.xoo-wsc-ft-btn-checkout');
    if (p && falta() > 0.0001) {
      e.preventDefault();
      e.stopPropagation();
      var b = box();
      b.scrollIntoView({ block: 'center', behavior: 'smooth' });
      b.classList.remove('dlpmin-pulso'); void b.offsetWidth; b.classList.add('dlpmin-pulso');
      return;
    }
    var s = e.target.closest && e.target.closest('.xoo-wsc-container a.dlpmin-seguir');
    if (s) {
      var u = new URL(s.href, location.href);
      if (u.pathname === location.pathname) {
        e.preventDefault();
        var x = document.querySelector('.xoo-wsc-close, .xoo-wsc-cart-close, .xoo-wsc-ft-btn-continue');
        if (x) x.click();
      }
    }
  }, true);
  if (window.jQuery) {
    jQuery(document.body).on('xoo_wsc_cart_updated wc_fragments_refreshed added_to_cart xoo_wsc_cart_toggled', function () { setTimeout(marcar, 50); });
  }
  if (window.jQuery) {
    /* Checkout: al agregar un postre se vuelve a calcular el pedido (y la caja se vuelve a pintar). */
    jQuery(document.body).on('added_to_cart', function () {
      if (document.querySelector('form.checkout')) jQuery(document.body).trigger('update_checkout');
    });
  }
  window.addEventListener('load', function () { setTimeout(marcar, 600); });
})();
