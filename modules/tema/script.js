/* API del modo: window.dlpTema.set('claro'|'oscuro'|'auto') guarda la elección del cliente y la aplica al instante. */
(function () {
  'use strict';
  var d = document.documentElement;
  window.dlpTema = {
    get: function () { try { return localStorage.getItem('dlp_tema') || 'auto'; } catch (e) { return 'auto'; } },
    set: function (t) {
      if (t !== 'claro' && t !== 'oscuro') t = 'auto';
      try { localStorage.setItem('dlp_tema', t); } catch (e) {}
      if (t === 'auto') d.removeAttribute('data-dlp-tema'); else d.setAttribute('data-dlp-tema', t);
      document.dispatchEvent(new CustomEvent('dlp-tema', { detail: t }));
    }
  };
})();
