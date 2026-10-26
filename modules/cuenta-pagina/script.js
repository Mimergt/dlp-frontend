/* Mi cuenta → Direcciones: eliminar y agregar con mapa (Leaflet de dlp-tiendas). */
(function ($) {
  'use strict';
  var A = window.dlpAcc;
  var $root = $('.dlpac-addr');
  if (!A || !$root.length) return;

  $root.on('click', '.dlpac-del', function () {
    var $c = $(this).closest('.dlpac-card');
    if (!window.confirm('¿Eliminar esta dirección?')) return;
    $.post(A.ajaxurl, { action: 'dlp_fe_lib_delete', nonce: A.nonce, id: $c.data('id') }).done(function () { window.location.reload(); });
  });

  var $modal = $('.dlpac-modal'), map = null, mapP = null, seq = 0, timer, point = null;
  var $cov = $modal.find('.dlpac-cov'), $save = $modal.find('.dlpac-save');

  function setCov(cls, txt) { $cov.attr('class', 'dlpac-cov ' + cls).text(txt || ''); }
  function validate() { $save.prop('disabled', !(point && $.trim($('#dlpac-name').val()))); }

  function place(ll) {
    if (mapP) mapP.then(function (api) { api.setPin(ll.lat, ll.lng); }, function () {});
    point = null; validate(); setCov('wait', 'Verificando cobertura...');
    var my = ++seq; clearTimeout(timer);
    timer = setTimeout(function () {
      $.post(A.ajaxurl, { action: 'dlp_tiendas_check_point', nonce: A.covNonce, lat: ll.lat, lng: ll.lng }).done(function (r) {
        if (my !== seq) return;
        var covered = r.ok || (r.reason && r.reason !== 'sin_cobertura' && r.reason !== 'punto_invalido');
        if (covered) { point = ll; setCov(r.ok ? 'ok' : 'warn', r.ok ? 'Tenemos cobertura en esta ubicación.' : 'Hay cobertura, pero ahora: ' + r.msg); }
        else { setCov('no', r.msg); }
        validate();
      }).fail(function () { if (my === seq) setCov('no', 'No pudimos verificar la cobertura. Intenta de nuevo.'); });
    }, 250);
  }

  function open() {
    $modal.prop('hidden', false); $('html').addClass('dlpac-lock');
    if (!mapP && window.DLP && DLP.pinMapa && A.map) {
      // Mapa 3D (o plano de respaldo) de dlp-tiendas; se crea al abrir la ventana por primera vez.
      mapP = DLP.pinMapa({
        el: 'dlpac-map', cfg: A.map,
        onTap: function (la, lo) { place({ lat: la, lng: lo }, 'pin'); },
        onDrag: function (la, lo) { place({ lat: la, lng: lo }, 'pin'); }
      }).then(function (api) {
        map = api;
        if (navigator.geolocation) {
          navigator.geolocation.getCurrentPosition(function (p) { api.setView(p.coords.latitude, p.coords.longitude, 16); }, function () {}, { timeout: 6000, maximumAge: 60000 });
        }
        return api;
      });
      mapP.catch(function () { setCov('no', 'No pudimos cargar el mapa. Intenta de nuevo.'); });
    }
    setTimeout(function () { if (map) map.resize(); }, 80);
  }
  function close() { $modal.prop('hidden', true); $('html').removeClass('dlpac-lock'); }

  $root.on('click', '.dlpac-add', open);
  $modal.on('click', '.dlpac-x', close);
  $modal.on('click', function (e) { if (e.target === this) close(); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape' && !$modal.prop('hidden')) close(); });
  $('#dlpac-name').on('input', validate);

  $save.on('click', function () {
    if (!point) return;
    $save.prop('disabled', true).text('Guardando...');
    $.post(A.ajaxurl, { action: 'dlp_fe_lib_save', nonce: A.nonce, name: $('#dlpac-name').val(), address: $('#dlpac-address').val(), ref: $('#dlpac-ref').val(), lat: point.lat, lng: point.lng })
      .done(function (r) {
        if (r && r.success) { window.location.reload(); return; }
        setCov('no', (r && r.data && r.data.msg) || 'No se pudo guardar.'); $save.text('Guardar dirección'); validate();
      }).fail(function () { setCov('no', 'No se pudo guardar. Intenta de nuevo.'); $save.text('Guardar dirección'); validate(); });
  });
})(jQuery);
