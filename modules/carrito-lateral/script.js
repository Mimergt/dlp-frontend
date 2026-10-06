/* Enlaces a /carrito/ llegan como /#carrito: abre el carrito lateral (Xootix) con su clase oficial xoo-wsc-cart-trigger. */
(function () {
  'use strict';
  function open() {
    if (location.hash !== '#carrito') return;
    var b = document.createElement('a');
    b.className = 'xoo-wsc-cart-trigger';
    b.href = '#carrito';
    b.style.display = 'none';
    document.body.appendChild(b);
    b.click();
    document.body.removeChild(b);
    try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
  }
  window.addEventListener('load', function () { setTimeout(open, 600); });
})();
