/* Pedido recibido: la hoja blanca llega al borde inferior (celular) y, si el estado cambia, recarga la página. */
(function () {
  'use strict';
  var root = document.querySelector('.dlpgr');
  if (!root) return;
  var sheet = root.querySelector('.dlpgr-sheet');

  function fit() {
    if (window.innerWidth >= 768) { sheet.style.minHeight = ''; return; }
    sheet.style.minHeight = '0px';
    sheet.style.minHeight = Math.max(0, window.innerHeight - (sheet.getBoundingClientRect().top + window.pageYOffset)) + 'px';
  }
  fit();
  window.addEventListener('load', fit);
  window.addEventListener('resize', fit);
  [300, 1200].forEach(function (ms) { setTimeout(fit, ms); });

  var status = root.getAttribute('data-status');
  if (['completed', 'cancelled', 'failed', 'refunded'].indexOf(status) !== -1) return;
  var every = Math.max(10, parseInt(root.getAttribute('data-every'), 10) || 20) * 1000;

  function check() {
    if (document.hidden) return;
    var fd = new FormData();
    fd.append('action', 'dlp_fe_gr_status');
    fd.append('order_id', root.getAttribute('data-order'));
    fd.append('order_key', root.getAttribute('data-key'));
    fetch(root.getAttribute('data-ajax'), { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (r) { if (r && r.success && r.data.status !== status) window.location.reload(); })
      .catch(function () {});
  }
  setInterval(check, every);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) check(); });
})();
