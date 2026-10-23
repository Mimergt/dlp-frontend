/*
 * Checkout con tarjetas. Solo reacomoda el DOM de WooCommerce (los campos y sus listeners, incluidos los de
 * dlp-tiendas, siguen siendo los mismos nodos). Si algo falta, no se rompe: cada pieza se mueve solo si existe.
 */
(function () {
  'use strict';
  var form = document.querySelector('form.checkout');
  if (!form || form.querySelector('.dlpck')) return;
  var cfg = (window.dlpFE && window.dlpFE['checkout-estilo']) || {};
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var svg = function (p) { return '<svg viewBox="0 0 24 24" aria-hidden="true">' + p + '</svg>'; };
  var ICON = {
    pin: svg('<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.4"/>'),
    user: svg('<circle cx="12" cy="8.2" r="3.7"/><path d="M4.8 20c.6-3.7 3.6-5.8 7.2-5.8s6.6 2.1 7.2 5.8"/>'),
    receipt: svg('<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>'),
    bag: svg('<path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>'),
    card: svg('<rect x="3" y="6" width="18" height="12" rx="2.5"/><path d="M3 10.5h18M7 15h3"/>'),
    back: svg('<path d="M15 5l-7 7 7 7"/>')
  };

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function card(icon, title, cls) {
    var s = el('section', 'dlpck-card ' + (cls || ''), '<h3><i>' + ICON[icon] + '</i>' + title + '</h3><div class="dlpck-body"></div>');
    return { root: s, body: s.lastChild };
  }
  function mv(sel, to) { var e = $(sel, form); if (e) to.appendChild(e); return e; }
  var grids = {};
  function fields(ids, to, name) {
    var g = el('div', 'dlpck-fields');
    ids.forEach(function (id) { mv('#' + id, g); });
    to.appendChild(g);
    grids[name] = g;
  }

  var wrap = el('div', 'dlpck'), main = el('div', 'dlpck-main'), side = el('div', 'dlpck-side');

  var c1 = card('pin', 'Entrega');
  mv('.selec-del', c1.body); mv('#dlp-delivery-box', c1.body); mv('#dlp-pickup-box', c1.body);
  fields(['billing_address_2_field', 'billing_address_1_field', 'billing_address_name_field', 'billing_state_field', 'billing_city_field'], c1.body, 'entrega');

  var c2 = card('user', 'Tus datos');
  fields(['billing_first_name_field', 'billing_last_name_field', 'billing_phone_field', 'billing_email_field'], c2.body, 'datos');

  var c3 = card('receipt', 'Factura y notas');
  fields(['billing_nitname_field', 'billing_nit_field'], c3.body, 'factura');
  var extras = el('div', 'dlpck-extras');
  mv('.woocommerce-account-fields', extras); mv('#order_comments_field', extras);
  c3.body.appendChild(extras);

  // Sección y orden vienen del editor de campos (data-dlp-card en el input, data-priority en el contenedor)
  form.querySelectorAll('[data-dlp-card]').forEach(function (inp) {
    var p = inp.closest('p.form-row'), g = grids[inp.getAttribute('data-dlp-card')];
    if (p && g) g.appendChild(p);
  });
  // Campos nuevos que ningún grupo reclamó: a "Tus datos"
  form.querySelectorAll('.woocommerce-billing-fields__field-wrapper > p.form-row').forEach(function (p) { if (grids.datos) grids.datos.appendChild(p); });
  Object.keys(grids).forEach(function (k) {
    Array.prototype.slice.call(grids[k].children)
      .sort(function (a, b) { return (parseInt(a.getAttribute('data-priority'), 10) || 0) - (parseInt(b.getAttribute('data-priority'), 10) || 0); })
      .forEach(function (n) { grids[k].appendChild(n); });
  });

  var c4 = card('bag', 'Tu pedido', 'dlpck-sum');
  mv('#order_review', c4.body);

  var c5 = card('card', 'Pago');
  mv('#payment', c5.body);
  var priv = $('.woocommerce-privacy-policy-text', c5.body);
  if (priv) c5.body.appendChild(el('div', 'dlpck-legal', priv.innerHTML));

  main.appendChild(c1.root); main.appendChild(c2.root); main.appendChild(c3.root);
  side.appendChild(c4.root); side.appendChild(c5.root);
  wrap.appendChild(main); wrap.appendChild(side);
  form.insertBefore(wrap, form.firstChild);

  // Encabezado con botón para regresar (antes de los avisos de sesión y cupón)
  var head = el('div', 'dlpck-head', '<a class="dlpck-back" href="' + location.origin + '/">' + ICON.back + '<span></span></a><h1>Finalizar compra</h1>');
  head.querySelector('span').textContent = cfg.texto_volver || 'Seguir pidiendo';
  head.querySelector('a').addEventListener('click', function (e) {
    var same = document.referrer && document.referrer.indexOf(location.origin) === 0 && document.referrer.indexOf('/finalizar-compra') === -1;
    if (same && history.length > 1) { e.preventDefault(); history.back(); }
  });
  var anchor = $('.woocommerce-form-login-toggle') || form;
  anchor.parentNode.insertBefore(head, anchor);

  document.body.classList.add('dlpck-on');

  // En Pickup no se pide dirección: solo la tienda
  function pickupMode() {
    var c = form.querySelector('input[name="woofood_order_type"]:checked');
    document.body.classList.toggle('dlpck-pickup', !!c && c.value === 'pickup');
  }
  pickupMode();
  form.addEventListener('change', function (e) { if (e.target.name === 'woofood_order_type') pickupMode(); });

  // Botón de pagar con el total (WooCommerce vuelve a dibujar #payment al cambiar los totales)
  function tune() {
    var b = $('#place_order');
    if (!b || b.querySelector('.dlpck-tot')) return;
    var t = $('.order-total .woocommerce-Price-amount');
    if (!t) return;
    var label = (b.textContent || '').trim() || 'Realizar pedido';
    b.innerHTML = '';
    b.appendChild(el('span', 'dlpck-lbl')).textContent = label;
    b.appendChild(el('span', 'dlpck-tot')).textContent = t.textContent.trim();
  }
  tune();
  if (window.jQuery) jQuery(document.body).on('updated_checkout', function () { setTimeout(tune, 30); });
  [600, 1500, 3000].forEach(function (ms) { setTimeout(tune, ms); });

  // ---- Avisos: tocar un error lleva al campo que hay que corregir -----------------------------------------------------------
  document.addEventListener('click', function (e) {
    var li = e.target.closest && e.target.closest('ul.woocommerce-error li[data-id]');
    if (!li) return;
    var id = li.getAttribute('data-id'), f = document.getElementById(id) || document.getElementById(id + '_field');
    if (!f) return;
    e.preventDefault();
    f.scrollIntoView({ behavior: 'smooth', block: 'center' });
    var inp = f.matches('input,select,textarea') ? f : f.querySelector('input,select,textarea');
    if (inp) setTimeout(function () { try { inp.focus({ preventScroll: true }); } catch (x) { inp.focus(); } }, 350);
  });

  // ---- Cupón en ventana emergente -------------------------------------------------------------------------------------------
  var cform = $('form.checkout_coupon'), cLink = $('.woocommerce-form-coupon-toggle .showcoupon');
  if (cform && cLink && window.jQuery) {
    var $j = window.jQuery;
    var backdrop = el('div', 'dlpck-cp-back'), box = el('div', 'dlpck-cp');
    box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.setAttribute('aria-label', 'Código de cupón');
    box.innerHTML = '<button type="button" class="dlpck-cp-x" aria-label="Cerrar">×</button><h3>Código de cupón</h3><p>Escribe tu código y toca aplicar.</p><div class="dlpck-cp-msg" aria-live="polite"></div>';
    box.appendChild(cform);
    backdrop.appendChild(box);
    document.body.appendChild(backdrop);
    cform.style.display = 'block';
    var input = cform.querySelector('input[name="coupon_code"]'), msg = box.querySelector('.dlpck-cp-msg');
    function openCp() { msg.innerHTML = ''; backdrop.classList.add('on', 'dlpck-back-on'); document.body.classList.add('dlpck-cp-open'); cLink.setAttribute('aria-expanded', 'true'); setTimeout(function () { input && input.focus(); }, 60); }
    function closeCp() { backdrop.classList.remove('on', 'dlpck-back-on'); document.body.classList.remove('dlpck-cp-open'); cLink.setAttribute('aria-expanded', 'false'); }
    // Se adelanta al manejador de WooCommerce (que desplegaba el formulario en la página)
    document.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('a.showcoupon')) { e.preventDefault(); e.stopImmediatePropagation(); openCp(); }
    }, true);
    box.querySelector('.dlpck-cp-x').addEventListener('click', closeCp);
    backdrop.addEventListener('mousedown', function (e) { if (e.target === backdrop) closeCp(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && backdrop.classList.contains('on')) closeCp(); });
    // Aplicar el cupón con el mismo endpoint de WooCommerce, pero mostrando el resultado dentro de la ventana
    box.addEventListener('submit', function (e) {
      e.preventDefault(); e.stopPropagation();
      var P = window.wc_checkout_params, code = (input.value || '').trim(), btn = cform.querySelector('button[type=submit]');
      if (!P || !code) { input.focus(); return; }
      btn.disabled = true; msg.innerHTML = '';
      $j.ajax({
        type: 'POST', dataType: 'html', url: P.wc_ajax_url.toString().replace('%%endpoint%%', 'apply_coupon'),
        data: { security: P.apply_coupon_nonce, coupon_code: code, billing_email: ($('form.checkout #billing_email') || {}).value || '' }
      }).done(function (html) {
        msg.innerHTML = html;
        if (/woocommerce-message/.test(html)) {
          input.value = '';
          $j(document.body).trigger('applied_coupon_in_checkout', [code]).trigger('update_checkout', { update_shipping_method: false });
          setTimeout(closeCp, 1100);
        }
      }).fail(function () { msg.innerHTML = '<div class="woocommerce-error">No se pudo aplicar el cupón. Intenta de nuevo.</div>'; })
        .always(function () { btn.disabled = false; });
    }, true);
  }
})();
