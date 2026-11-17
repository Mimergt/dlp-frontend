/*
 * Checkout: cuando el envío falla, en el celular el aviso de WooCommerce puede quedar arriba, fuera de pantalla. Se lleva la vista
 * al campo que falta (o a la ubicación / la tienda), se marca y se muestra un aviso fijo.
 */
(function ($) {
  'use strict';
  if (!$) return;
  var bar = null, timer = null;

  function aviso(texto) {
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'dlpce-bar'; bar.setAttribute('role', 'alert');
      bar.addEventListener('click', function () { hide(); });
      document.body.appendChild(bar);
    }
    bar.textContent = texto; bar.classList.add('on');
    clearTimeout(timer); timer = setTimeout(hide, 9000);
  }
  function hide() { if (bar) bar.classList.remove('on'); }
  function go(el) {
    if (!el || !el.scrollIntoView) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    var inp = el.matches && el.matches('input,select,textarea') ? el : el.querySelector && el.querySelector('input:not([type=hidden]),select,textarea');
    if (inp) setTimeout(function () { try { inp.focus({ preventScroll: true }); } catch (e) { inp.focus(); } }, 450);
  }
  function etiqueta(row) {
    var l = row && row.querySelector('label');
    return l ? l.textContent.replace(/\*|opcional/gi, '').replace(/\s+/g, ' ').trim() : '';
  }

  // WooCommerce anima el scroll hacia el aviso de arriba; se espera a que termine y, si el campo sigue fuera de pantalla, se vuelve a él.
  function irA(el) {
    if (!el) return;
    $('html, body').stop(true);
    setTimeout(function () { go(el); }, 150);
    setTimeout(function () {
      var r = el.getBoundingClientRect();
      if (r.top < 70 || r.bottom > window.innerHeight - 20) go(el);
    }, 1400);
  }

  $(document.body).on('checkout_error', function () {
    $('.dlpce-err').removeClass('dlpce-err');
    var $notice = $('.woocommerce-error').first();
    var ids = [], faltan = [], primero = null;
    $notice.find('li[data-id]').each(function () { ids.push($(this).attr('data-id')); });
    ids.forEach(function (id) {
      var row = document.getElementById(id + '_field');
      if (!row) return;
      $(row).addClass('dlpce-err');
      var t = etiqueta(row); if (t) faltan.push(t);
      if (!primero) primero = row;
    });
    if (primero) {
      aviso('Falta: ' + faltan.join(', ') + '. Toca este aviso para cerrarlo.');
      irA(primero);
      return;
    }
    // Errores sin campo (ubicación, tienda, hora, mínimo...): se lleva la vista al bloque correspondiente o al aviso.
    var txt = ($notice.text() || '').toLowerCase(), destino = null;
    if (/mapa|ubicaci|cobertura|llegamos/.test(txt)) destino = document.getElementById('dlp-delivery-box');
    else if (/tienda|recoger|recogeras|hora/.test(txt)) destino = document.getElementById('dlp-pickup-box');
    var msg = ($notice.find('li').first().text() || $notice.text() || 'Revisa tu pedido').replace(/\s+/g, ' ').trim();
    aviso(msg.length > 140 ? msg.slice(0, 137) + '…' : msg);
    irA(destino && destino.offsetParent !== null ? destino : ($notice[0] || null));
  });

  $(document.body).on('input change', '.dlpce-err input, .dlpce-err select, .dlpce-err textarea', function () {
    $(this).closest('.dlpce-err').removeClass('dlpce-err');
  });
})(window.jQuery);
