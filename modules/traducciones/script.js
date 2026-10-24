/* Textos en inglés que los plugins de pago ponen en atributos (tooltips y lectores de pantalla) o dentro de sus iframes. */
(function () {
  var MAP = { 'required': 'obligatorio', 'secure payment field': 'campo de pago seguro', 'empty': '' };
  function fix(root) {
    [].forEach.call((root || document).querySelectorAll('abbr.required[title], iframe[title]'), function (e) {
      var t = e.getAttribute('title');
      if (t && Object.prototype.hasOwnProperty.call(MAP, t)) {
        MAP[t] ? e.setAttribute('title', MAP[t]) : e.removeAttribute('title');
      }
    });
  }
  fix();
  if (window.MutationObserver) {
    var pend = false;
    new MutationObserver(function () {
      if (pend) return; pend = true;
      setTimeout(function () { pend = false; fix(); }, 150);
    }).observe(document.body, { childList: true, subtree: true });
  }
})();
