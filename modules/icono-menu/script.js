/* Íconos del header de Divi: Menú (enlace a /menu/) y Restaurantes (enlace a /restaurantes/), a la izquierda del texto del botón. */
(function () {
  var MAPA = [
    { re: /\/menu$/, tpl: 'dlp-im-tpl', cls: 'dlp-im-btn' },
    { re: /\/restaurantes$/, tpl: 'dlp-ip-tpl', cls: 'dlp-ip-btn' }
  ];
  function put() {
    document.querySelectorAll('.et-l--header a.et_pb_button').forEach(function (a) {
      if (a.classList.contains('dlp-ic-btn')) { return; }
      var h = (a.getAttribute('href') || '').replace(/[?#].*$/, '').replace(/\/+$/, '');
      var m = MAPA.filter(function (x) { return x.re.test(h); })[0];
      var tpl = m && document.getElementById(m.tpl);
      if (!tpl) { return; }
      var svg = tpl.content.firstElementChild.cloneNode(true);
      /* los id de máscaras deben ser únicos si el header se clona */
      var uid = 'dlpimh' + Math.random().toString(36).slice(2, 7);
      svg.innerHTML = svg.innerHTML.replace(/dlpim\d+/g, uid);
      a.classList.add('dlp-ic-btn', m.cls);
      a.insertBefore(svg, a.firstChild);
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', put); } else { put(); }
  window.addEventListener('load', put);
})();
