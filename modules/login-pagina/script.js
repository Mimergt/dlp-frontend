/* Página de ingreso: envía por Ajax y va a Mi cuenta (o a donde pidió redirect_to). */
(function () {
  'use strict';
  var root = document.querySelector('.dlplg');
  var cfg = window.dlpLg;
  if (!root || !cfg) return;
  var form = root.querySelector('form'), msg = root.querySelector('.dlplg-msg'), btn = root.querySelector('.dlplg-go');

  // Celular: la hoja blanca llega hasta el borde inferior (la altura del header de Divi varía)
  function fit() {
    if (window.innerWidth >= 768) { form.style.minHeight = ''; return; }
    form.style.minHeight = '0px';
    form.style.minHeight = Math.max(0, window.innerHeight - (form.getBoundingClientRect().top + window.pageYOffset)) + 'px';
  }
  fit();
  window.addEventListener('load', fit);
  window.addEventListener('resize', fit);
  [300, 1200].forEach(function (ms) { setTimeout(fit, ms); });

  function show(t) { msg.textContent = t; msg.hidden = !t; }
  root.querySelector('.dlplg-eye').addEventListener('click', function () {
    var i = root.querySelector('input[name=pwd]'), v = i.type === 'password';
    i.type = v ? 'text' : 'password'; this.textContent = v ? 'Ocultar' : 'Ver';
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    show('');
    var fd = new FormData(form);
    fd.append('action', 'dlp_fe_login');
    fd.append('redirect_to', root.getAttribute('data-redirect') || '');
    btn.disabled = true; btn.textContent = 'Ingresando...';
    fetch(cfg.ajaxurl, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (r) {
        if (r && r.success) { window.location.href = r.data.to || cfg.home; return; }
        show((r && r.data && r.data.msg) || 'No se pudo ingresar. Intenta de nuevo.');
        btn.disabled = false; btn.textContent = 'Ingresar';
      })
      .catch(function () { show('No se pudo conectar. Revisa tu conexión e intenta de nuevo.'); btn.disabled = false; btn.textContent = 'Ingresar'; });
  });
})();
