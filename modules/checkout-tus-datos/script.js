/*
 * Checkout: «Tus datos» con cuenta automática. Mueve el campo de contraseña (opcional) debajo del correo, oculta la casilla
 * «¿Crear una cuenta?» y avisa si el correo ya tiene cuenta. Los campos son los mismos nodos de WooCommerce.
 */
(function () {
  'use strict';
  var cfg = (window.dlpFE && window.dlpFE['checkout-tus-datos']) || {};
  var T = window.dlpTD || {};
  var form = document.querySelector('form.checkout');
  if (!form) return;
  var tries = 0;
  (function wait() {
    var email = document.getElementById('billing_email_field');
    var pwRow = document.getElementById('account_password_field');
    if (!email || !pwRow || !email.parentNode || !form.querySelector('.dlpck')) {
      if (++tries < 40) return setTimeout(wait, 100);
      if (!pwRow) return; // con sesión iniciada no hay campo de cuenta
    }
    build(email, pwRow);
  })();

  function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

  function build(emailRow, pwRow) {
    if (document.querySelector('.dlptd')) return;
    var min = parseInt(cfg.min, 10) || 8;
    var cb = document.getElementById('createaccount');
    if (cb) { cb.checked = true; var cr = cb.closest('p'); if (cr) cr.style.display = 'none'; }

    var box = el('div', 'dlptd');
    box.appendChild(el('p', 'dlptd-note', esc(cfg.nota || 'Crearemos tu cuenta automáticamente con este correo para que sigas tus pedidos.')));
    var wrap = el('div', 'dlptd-pw');
    var input = pwRow.querySelector('input');
    var lab = pwRow.querySelector('label'); if (lab) lab.innerHTML = esc(cfg.etiqueta || 'Contraseña (opcional)');
    pwRow.classList.remove('validate-required'); pwRow.removeAttribute('data-priority');
    pwRow.style.display = '';
    var holder = pwRow.querySelector('.woocommerce-input-wrapper') || input.parentNode;
    var eye = el('button', 'dlptd-eye', 'Ver'); eye.type = 'button'; eye.setAttribute('aria-label', 'Mostrar contraseña');
    holder.style.position = 'relative'; holder.appendChild(eye);
    eye.addEventListener('click', function () { var show = input.type === 'password'; input.type = show ? 'text' : 'password'; eye.textContent = show ? 'Ocultar' : 'Ver'; });
    input.setAttribute('autocomplete', 'new-password');
    wrap.appendChild(pwRow);
    var hint = el('small', 'dlptd-hint', 'Mínimo ' + min + ' caracteres.'); wrap.appendChild(hint);
    box.appendChild(wrap);

    var ex = el('div', 'dlptd-exists', '<b>Ya tienes una cuenta con este correo.</b><span>Inicia sesión para usar tus direcciones y ver tus pedidos, o sigue como invitado.</span>' +
      '<div class="dlptd-btns"><button type="button" class="dlptd-login">Iniciar sesión</button><button type="button" class="dlptd-guest">Seguir como invitado</button></div>');
    ex.hidden = true; box.appendChild(ex);
    emailRow.parentNode.parentNode.insertBefore(box, emailRow.parentNode.nextSibling);

    var emailInput = document.getElementById('billing_email'), last = '', guest = {};
    function showExists(on) { ex.hidden = !on; wrap.style.display = on ? 'none' : ''; }
    function check() {
      if (!emailInput || !window.fetch) return;
      var v = emailInput.value.trim().toLowerCase();
      if (!/^\S+@\S+\.\S+$/.test(v)) { showExists(false); last = ''; return; }
      if (v === last) return; last = v;
      if (guest[v]) { showExists(false); return; }
      var fd = new FormData(); fd.append('action', 'dlp_fe_email_existe'); fd.append('email', v);
      fetch(T.ajaxurl || '/wp-admin/admin-ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (emailInput.value.trim().toLowerCase() === v) showExists(!!(j && j.exists) && !guest[v]); })
        .catch(function () {});
    }
    if (emailInput) { emailInput.addEventListener('change', check); emailInput.addEventListener('blur', check); emailInput.addEventListener('input', function () { if (ex.hidden === false) check(); }); if (emailInput.value) check(); }
    ex.querySelector('.dlptd-login').addEventListener('click', function () {
      document.dispatchEvent(new CustomEvent('dlplm:open', { detail: { email: emailInput ? emailInput.value.trim() : '' } }));
    });
    ex.querySelector('.dlptd-guest').addEventListener('click', function () { guest[last] = true; showExists(false); });

    // Si escriben contraseña, que tenga el largo mínimo (vacía = se la enviamos por correo).
    if (window.jQuery) {
      window.jQuery(form).on('checkout_place_order', function () {
        if (input.value && input.value.length < min) {
          hint.className = 'dlptd-hint is-err'; hint.textContent = 'La contraseña debe tener al menos ' + min + ' caracteres, o déjala vacía.';
          input.focus(); if (input.scrollIntoView) input.scrollIntoView({ block: 'center', behavior: 'smooth' });
          return false;
        }
        return true;
      });
    }
  }
})();
