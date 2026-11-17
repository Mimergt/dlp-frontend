(function () {
  'use strict';
  var min = (window.dlpRC && window.dlpRC.min) || 8;
  document.querySelectorAll('.dlprc-eye').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = b.parentNode.querySelector('input'); if (!i) return;
      var show = i.type === 'password'; i.type = show ? 'text' : 'password'; b.textContent = show ? 'Ocultar' : 'Ver';
    });
  });
  var f = document.querySelector('.dlprc-form');
  if (!f) return;
  f.addEventListener('submit', function (e) {
    var msg = f.querySelector('.dlprc-msg'), p1 = f.querySelector('#password_1'), p2 = f.querySelector('#password_2'), u = f.querySelector('#user_login'), t = '';
    if (p1 && p2) {
      if (p1.value.length < min) t = 'La contraseña debe tener al menos ' + min + ' caracteres.';
      else if (p1.value !== p2.value) t = 'Las contraseñas no coinciden.';
    } else if (u && !u.value.trim()) { t = 'Escribe el correo de tu cuenta.'; }
    if (t) {
      e.preventDefault(); e.stopImmediatePropagation(); // evita que el script de WooCommerce deje el botón deshabilitado
      var go = f.querySelector('.dlprc-go'); if (go) setTimeout(function () { go.disabled = false; }, 0);
      if (!msg) { msg = document.createElement('div'); msg.className = 'dlprc-msg'; msg.setAttribute('role', 'alert'); f.insertBefore(msg, f.firstChild); }
      msg.textContent = t; msg.hidden = false;
    }
  }, true);
})();
