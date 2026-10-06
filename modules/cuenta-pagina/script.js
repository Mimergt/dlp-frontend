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

  var $modal = $('.dlpac-modal'), map, pin, seq = 0, timer, point = null;
  var $cov = $modal.find('.dlpac-cov'), $save = $modal.find('.dlpac-save');

  function setCov(cls, txt) { $cov.attr('class', 'dlpac-cov ' + cls).text(txt || ''); }
  function validate() { $save.prop('disabled', !(point && $.trim($('#dlpac-name').val()))); }

  function place(ll) {
    if (!pin) { pin = L.marker(ll, { draggable: true }).addTo(map); pin.on('dragend', function () { place(pin.getLatLng()); }); }
    else { pin.setLatLng(ll); }
    point = null; validate(); setCov('wait', 'Verificando cobertura...');
    var my = ++seq; clearTimeout(timer);
    timer = setTimeout(function () {
      $.post(A.ajaxurl, { action: 'dlp_tiendas_check_point', nonce: A.covNonce, lat: ll.lat, lng: ll.lng }).done(function (r) {
        if (my !== seq) return;
        var covered = r.ok || (r.reason && r.reason !== 'sin_sobertura' && r.reason !== 'sin_cobertura' && r.reason !== 'punto_invalido');
        if (covered) { point = ll; setCov(r.ok ? 'ok' : 'warn', r.ok ? 'Tenemos cobertura en esta ubicación.' : 'Hay cobertura, pero ahora: ' + r.msg); }
        else { setCov('no', r.msg); }
        validate();
      }).fail(function () { if (my === seq) setCov('no', 'No pudimos verificar la cobertura. Intenta de nuevo.'); });
    }, 250);
  }

  function open() {
    $modal.prop('hidden', false); $('html').addClass('dlpac-lock');
    if (!map && window.L && A.map) {
      var C = A.map;
      map = L.map('dlpac-map', { center: C.center, zoom: C.zoom, minZoom: C.minZoom, maxZoom: C.maxZoom, maxBounds: C.bounds, maxBoundsViscosity: 1 });
      try { protomapsL.leafletLayer({ url: C.tilesUrl, flavor: 'light', lang: 'es', attribution: 'EPIC.GT · Protomaps © OpenStreetMap' }).addTo(map); } catch (e) {}
      map.on('click', function (e) { place(e.latlng); });
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (p) { map.setView([p.coords.latitude, p.coords.longitude], 16); }, function () {}, { timeout: 6000, maximumAge: 60000 });
      }
    }
    setTimeout(function () { if (map) map.invalidateSize(); }, 80);
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
