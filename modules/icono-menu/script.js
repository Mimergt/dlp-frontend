/* Ícono del Menú en el header de Divi: se coloca a la izquierda del texto del botón que apunta a /menu/. */
(function () {
  function put() {
    var tpl = document.getElementById('dlp-im-tpl');
    if (!tpl) { return; }
    document.querySelectorAll('.et-l--header a.et_pb_button').forEach(function (a) {
      if (a.classList.contains('dlp-im-btn')) { return; }
      var h = (a.getAttribute('href') || '').replace(/[?#].*$/, '').replace(/\/+$/, '');
      if (!/\/menu$/.test(h)) { return; }
      var svg = tpl.content.firstElementChild.cloneNode(true);
      /* los id de máscaras deben ser únicos si el header se clona */
      var uid = 'dlpimh' + Math.random().toString(36).slice(2, 7);
      svg.innerHTML = svg.innerHTML.replace(/dlpim\d+/g, uid);
      a.classList.add('dlp-im-btn');
      a.insertBefore(svg, a.firstChild);
    });
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', put); } else { put(); }
  window.addEventListener('load', put);
})();
