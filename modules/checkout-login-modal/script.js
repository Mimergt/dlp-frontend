/*
 * Checkout: login en ventana. Mueve el formulario de acceso de WooCommerce a un modal y lo envía por fetch;
 * si entra, recarga el checkout (carrito intacto) y repone los datos que el cliente ya había escrito.
 */
(function () {
  'use strict';
  var cfg = (window.dlpFE && window.dlpFE['checkout-login-modal']) || {};
  var KEY = 'dlplm_draft';
  var store = { get: function () { try { return window.sessionStorage.getItem(KEY); } catch (e) { return null; } }, set: function (v) { try { window.sessionStorage.setItem(KEY, v); } catch (e) {} }, del: function () { try { window.sessionStorage.removeItem(KEY); } catch (e) {} } };

  /* --- Datos escritos antes de iniciar sesión --- */
  function fieldsOf(form) { return [].slice.call(form.querySelectorAll('input, select, textarea')).filter(function (f) { return f.name && !f.disabled && !/password|^_|nonce|woocommerce-|payment_method|terms|createaccount/i.test(f.name) && !/^(hidden|password|submit|button|file|checkbox|radio)$/i.test(f.type); }); }
  function saveDraft() {
    var co = document.querySelector('form.checkout'); if (!co) return;
    var d = {}; fieldsOf(co).forEach(function (f) { if (f.value) d[f.name] = f.value; });
    store.set(JSON.stringify({ t: Date.now(), d: d }));
  }
  function restoreDraft() {
    var raw = store.get(); if (!raw) return;
    var obj; try { obj = JSON.parse(raw); } catch (e) { store.del(); return; }
    if (!obj || Date.now() - obj.t > 10 * 60 * 1000) { store.del(); return; }
    var co = document.querySelector('form.checkout'); if (!co) return;
    var done = false;
    function apply() {
      fieldsOf(co).forEach(function (f) {
        if (f.value || obj.d[f.name] == null) return;
        f.value = obj.d[f.name];
        f.dispatchEvent(new Event('input', { bubbles: true })); f.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
    setTimeout(function () { apply(); }, 700);
    setTimeout(function () { apply(); if (!done) { done = true; store.del(); } }, 2200);
  }

  var toggle = document.querySelector('.woocommerce-form-login-toggle');
  var form = document.querySelector('form.woocommerce-form-login');
  if (!toggle || !form) { restoreDraft(); return; } // con sesión iniciada: solo repone datos si venimos del modal

  /* --- Ventana --- */
  var overlay = document.createElement('div');
  overlay.className = 'dlplm'; overlay.setAttribute('aria-hidden', 'true');
  overlay.innerHTML = '<div class="dlplm-box" role="dialog" aria-modal="true" aria-labelledby="dlplm-t">' +
    '<button type="button" class="dlplm-x" aria-label="Cerrar">&times;</button>' +
    '<h2 class="dlplm-title" id="dlplm-t"></h2><p class="dlplm-intro"></p><div class="dlplm-err" role="alert"></div><div class="dlplm-form"></div></div>';
  overlay.querySelector('.dlplm-title').textContent = cfg.titulo || 'Inicia sesión';
  overlay.querySelector('.dlplm-intro').textContent = cfg.intro || 'Entra con tu cuenta y vuelves a tu pedido con todo listo.';
  form.style.display = '';
  overlay.querySelector('.dlplm-form').appendChild(form); // el mismo formulario (con su nonce y campos)
  document.body.appendChild(overlay);

  var err = overlay.querySelector('.dlplm-err'), btn = form.querySelector('button[type=submit], input[type=submit]');
  var lastFocus = null;
  function open() {
    lastFocus = document.activeElement; err.className = 'dlplm-err'; err.textContent = '';
    overlay.classList.add('is-open'); overlay.setAttribute('aria-hidden', 'false'); document.documentElement.classList.add('dlplm-open');
    setTimeout(function () { var u = form.querySelector('#username'); if (u) u.focus(); }, 60);
  }
  function close() {
    overlay.classList.remove('is-open'); overlay.setAttribute('aria-hidden', 'true'); document.documentElement.classList.remove('dlplm-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  // En captura, para que el desplegable original de WooCommerce no se ejecute.
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a.showlogin');
    if (!a) return;
    e.preventDefault(); e.stopPropagation(); open();
  }, true);
  // Otros módulos pueden abrirlo (p. ej. «Ya tienes una cuenta con este correo»), con el correo ya escrito.
  document.addEventListener('dlplm:open', function (e) {
    open();
    var u = form.querySelector('#username');
    if (u && e.detail && e.detail.email) { u.value = e.detail.email; var p = form.querySelector('#password'); if (p) p.focus(); }
  });
  overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
  overlay.querySelector('.dlplm-x').addEventListener('click', close);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('is-open')) close(); });

  function showError(msg) { err.textContent = msg; err.className = 'dlplm-err is-on'; }
  form.addEventListener('submit', function (e) {
    if (!window.fetch || !window.FormData || !window.DOMParser) return; // navegador viejo: envío normal
    e.preventDefault();
    var u = form.querySelector('#username'), p = form.querySelector('#password');
    if (!u || !u.value.trim() || !p || !p.value) { showError('Escribe tu usuario o correo y tu contraseña.'); return; }
    err.className = 'dlplm-err';
    if (btn) { btn.disabled = true; btn.setAttribute('data-txt', btn.textContent || btn.value || ''); if (btn.textContent) btn.textContent = 'Entrando…'; }
    var fd = new FormData(form); fd.set('login', 'Acceder');
    var restore = function () { if (btn) { btn.disabled = false; if (btn.textContent) btn.textContent = btn.getAttribute('data-txt'); } };
    fetch(form.getAttribute('action') || window.location.href, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'follow' })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        if (doc.querySelector('form.woocommerce-form-login')) { // sigue sin sesión: mostrar el motivo
          var li = doc.querySelector('.woocommerce-error li, .woocommerce-error, .woocommerce-notices-wrapper .woocommerce-info');
          var t = li ? li.textContent.replace(/\s+/g, ' ').trim() : '';
          showError(t || 'No pudimos iniciar sesión. Revisa tus datos e inténtalo de nuevo.');
          restore();
          return;
        }
        saveDraft();
        window.location.reload();
      })
      .catch(function () { showError('Sin conexión. Inténtalo de nuevo.'); restore(); });
  });
})();
