/*
 * Libreta de direcciones en el checkout. Elegir una dirección usa el mismo camino que usaba Fr Address Book hacia
 * dlp-tiendas (radio fabfw_address_billing_id + window.fabfw_select_address): así dlp-tiendas pone el pin, verifica la
 * cobertura y el horario y habilita o bloquea el botón de pagar, sin cambios en ese plugin.
 */
(function ($) {
  'use strict';
  var L = window.dlpLib;
  var cfg = (window.dlpFE && window.dlpFE['checkout-libreta']) || {};
  var $box = $('#dlp-delivery-box');
  if (!L || !$box.length) return;

  window.fabfw_select_address = { addresses: {} };
  var $wrap = $('<div class="dlplib"></div>');
  var $list = $('<div class="dlplib-list"></div>');
  var $radios = $('<span class="dlplib-radios" hidden></span>');
  var selected = null;

  function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

  function render() {
    $list.empty(); $radios.empty();
    L.list.forEach(function (a) {
      window.fabfw_select_address.addresses[a.id] = { dlp_lat: a.lat, dlp_lng: a.lng };
      var $r = $('<input type="radio" name="fabfw_address_billing_id">').val(a.id);
      $radios.append($r);
      var chip = a.ok ? '<span class="dlplib-chip ok">Abierta</span>' : '<span class="dlplib-chip no">Cerrada</span>';
      var $c = $(
        '<div class="dlplib-card' + (a.ok ? '' : ' is-off') + (selected === a.id ? ' is-on' : '') + '" role="button" tabindex="' + (a.ok ? 0 : -1) + '" aria-disabled="' + (a.ok ? 'false' : 'true') + '" data-id="' + esc(a.id) + '">' +
          '<div class="dlplib-main"><b>' + esc(a.name) + '</b> ' + chip +
          '<div class="dlplib-addr">' + esc(a.address || a.ref || 'Punto en el mapa') + '</div>' +
          (a.store ? '<div class="dlplib-store">' + esc(a.store) + '</div>' : '') +
          (a.ok ? '' : '<div class="dlplib-msg">' + esc(a.msg) + '</div>') + '</div>' +
          '<button type="button" class="dlplib-del" aria-label="Eliminar dirección" title="Eliminar">×</button></div>'
      );
      $list.append($c);
    });
  }

  function choose(id) {
    var a = L.list.filter(function (x) { return x.id === id; })[0];
    if (!a || !a.ok) return;
    selected = id;
    $radios.find('input').prop('checked', false).filter(function () { return this.value === id; }).prop('checked', true).trigger('change');
    $('#billing_address_2').val(a.address || '').trigger('change');
    $('#billing_address_name').val(a.ref || '').trigger('change');
    render();
    refreshSave();
  }

  $list.on('click keydown', '.dlplib-card', function (e) {
    if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
    if ($(e.target).closest('.dlplib-del').length) return;
    e.preventDefault();
    choose($(this).data('id'));
  });
  $list.on('click', '.dlplib-del', function (e) {
    e.stopPropagation();
    var id = $(this).closest('.dlplib-card').data('id');
    if (!window.confirm('¿Eliminar esta dirección?')) return;
    $.post(L.ajaxurl, { action: 'dlp_fe_lib_delete', nonce: L.nonce, id: id }).done(function () {
      L.list = L.list.filter(function (x) { return x.id !== id; });
      if (selected === id) { selected = null; }
      render(); toggleBlock();
    });
  });

  // "Guardar esta dirección": aparece cuando hay un punto marcado a mano o por GPS
  var $save = $(
    '<div class="dlplib-save" hidden><label class="dlplib-chk"><input type="checkbox" name="dlp_save_address" value="1"> <span>Guardar esta dirección</span></label>' +
    '<input type="text" name="dlp_address_name" maxlength="40" placeholder="Nombre: Casa, Trabajo…" class="dlplib-name" hidden></div>'
  );
  var $chk = $save.find('input[type=checkbox]'), $nm = $save.find('.dlplib-name');
  $chk.on('change', function () { $nm.prop('hidden', !this.checked); if (this.checked) $nm.trigger('focus'); });

  function refreshSave() {
    var has = $('#billing_dlp_lat').val() && $('#billing_dlp_lng').val();
    var src = $('#dlp_geo_source').val();
    var show = L.logged && has && src !== 'saved' && L.list.length < Math.max(L.max, 1) + 1;
    $save.prop('hidden', !show);
    if (!show) { $chk.prop('checked', false); $nm.prop('hidden', true); }
  }

  function toggleBlock() { $wrap.find('.dlplib-title, .dlplib-list').prop('hidden', !L.list.length); }

  var title = $('<div class="dlplib-title"></div>').text(cfg.titulo || 'Tus direcciones');
  $wrap.append(title, $list, $radios, $save);
  $box.find('.dlp-actions').first().before($wrap);
  render(); toggleBlock(); refreshSave();
  setInterval(refreshSave, 700);
  if (!L.logged) {
    $wrap.append('<p class="dlplib-hint">Inicia sesión para guardar tus direcciones y pedir más rápido.</p>');
  }
})(jQuery);
