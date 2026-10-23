/*
 * Producto en modal (hoja inferior). Ver module.php.
 *
 * Enlaces directos: #producto-<slug> o #producto-<id> abren el producto al cargar la página, por ejemplo
 *   https://delpuente.com.gt/#producto-combos-las-favoritas
 * Al abrir un producto desde una tarjeta se agrega ese hash a la URL (se puede copiar y compartir) y el botón
 * "Atrás" del teléfono cierra el modal.
 */
(function ($) {
  'use strict';
  var cfg = window.dlpQV || {};
  var HASH_RE = /^#producto-(.+)$/;
  var $modal, $body, lastFocus = null, token = 0, current = '', pushed = false;

  function build() {
    if ($modal) return;
    $modal = $(
      '<div class="dlpqv" aria-hidden="true">' +
        '<div class="dlpqv-overlay" data-dlpqv-close></div>' +
        '<div class="dlpqv-dialog" role="dialog" aria-modal="true" aria-labelledby="dlpqv-title" tabindex="-1">' +
          '<div class="dlpqv-grab" data-dlpqv-close></div>' +
          '<button type="button" class="dlpqv-close" data-dlpqv-close aria-label="Cerrar">&times;</button>' +
          '<div class="dlpqv-body"></div>' +
        '</div>' +
      '</div>'
    ).appendTo(document.body);
    $body = $modal.find('.dlpqv-body');
  }

  function isOpen() { return $modal && $modal.hasClass('is-open'); }

  function show() {
    lastFocus = document.activeElement;
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    $('html').addClass('dlpqv-lock');
    $modal.find('.dlpqv-dialog').trigger('focus');
  }

  function hide() {
    token++;
    current = '';
    $modal.removeClass('is-open').attr('aria-hidden', 'true');
    $('html').removeClass('dlpqv-lock');
    $body.empty();
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  // Cierre iniciado por el usuario (×, fondo, Esc, añadir al carrito): también limpia el hash.
  function close() {
    if (!isOpen()) return;
    hide();
    if (HASH_RE.test(location.hash)) {
      if (pushed) { pushed = false; history.back(); }
      else history.replaceState(null, '', location.pathname + location.search);
    }
    pushed = false;
  }

  function setHash(key) {
    var h = '#producto-' + key;
    if (location.hash === h) return;
    history.pushState(null, '', h);
    pushed = true;
  }

  // ---- Contenido ----------------------------------------------------------------------------------------------

  function money(n) {
    var sym = $body.find('.dlpqv-tag .woocommerce-Price-currencySymbol').first().text() || 'Q';
    return sym + (Math.round(n * 100) / 100).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  }

  /*
   * Total calculado aquí con las mismas reglas de los Add-Ons (flat_fee: una vez; quantity_based: por cada unidad;
   * percentage_based: % del precio). Los Add-Ons solo calculan su propio subtotal cuando TODOS los obligatorios
   * están completos, por eso no se depende de él para mostrar el total mientras se arma el pedido.
   */
  function calcTotal($form) {
    var base = parseFloat($body.find('.dlpqv-hero').attr('data-price')) || 0;
    var qty = parseFloat($form.find('input.qty').val()) || 1;
    var perUnit = base, flat = 0;
    function add(price, type, mult) {
      price = (parseFloat(price) || 0) * (mult == null ? 1 : mult);
      if (!price) return;
      if (type === 'quantity_based') perUnit += price;
      else if (type === 'percentage_based') perUnit += base * price / 100;
      else flat += price;
    }
    $form.find('select.wc-pao-addon-field option:selected').each(function () { add($(this).attr('data-price'), $(this).attr('data-price-type')); });
    $form.find('input.wc-pao-addon-radio:checked, input.wc-pao-addon-checkbox:checked').each(function () { add($(this).attr('data-price'), $(this).attr('data-price-type')); });
    $form.find('input.wc-pao-addon-input-multiplier').each(function () { add($(this).attr('data-price'), $(this).attr('data-price-type'), parseFloat($(this).val()) || 0); });
    return perUnit * qty + flat;
  }

  function updateTotal() {
    var $form = $body.find('form.cart').first();
    if (!$form.length) return;
    $body.find('.dlpqv-tot').text(money(calcTotal($form)));
  }

  function stepper($in, min, max) {
    if (!$in.length || $in.parent().hasClass('dlpqv-step-wrap')) return;
    var $w = $in.wrap('<span class="dlpqv-step-wrap"></span>').parent();
    $('<button type="button" class="dlpqv-step" data-step="-1" aria-label="Menos">−</button>').insertBefore($in);
    $('<button type="button" class="dlpqv-step" data-step="1" aria-label="Más">+</button>').insertAfter($in);
    $w.data({ min: min, max: max });
  }

  // ---- Grupos con total exacto (ej. 3 bebidas a elegir entre varias) ---------------------------------------
  function groupInputs($form, t) { return $form.find('input[data-dlpqv-group]').filter(function () { return $(this).attr('data-dlpqv-group') === t; }); }
  function groupSum($ins) { var s = 0; $ins.each(function () { s += parseInt($(this).val(), 10) || 0; }); return s; }
  function groupClamp($in) {
    var $form = $in.closest('form.cart'), t = $in.attr('data-dlpqv-group'), n = parseInt($in.attr('data-dlpqv-n'), 10);
    if (isNaN(n)) return; // grupo libre ([grupo:Título=*]): sin total que respetar
    var others = groupSum(groupInputs($form, t).not($in));
    var v = Math.max(0, Math.min(parseInt($in.val(), 10) || 0, n - others));
    if (String(v) !== $in.val()) $in.val(v);
  }
  function groupSync($form) {
    var wait = false;
    $form.find('.dlpqv-grp:not(.dlpqv-acc)').each(function () {
      var t = $(this).attr('data-g'), $ins = groupInputs($form, t), n = parseInt($ins.first().attr('data-dlpqv-n'), 10), sum = groupSum($ins), libre = isNaN(n);
      $(this).find('.dlpqv-grp-c').text(libre ? (sum ? sum + (sum === 1 ? ' elegida' : ' elegidas') : 'Opcional') : sum + ' de ' + n).closest('.dlpqv-grp').toggleClass('done', libre ? sum > 0 : sum === n);
      var resumen = [];
      $ins.each(function () {
        var q = parseInt($(this).val(), 10) || 0;
        if (q > 0) resumen.push(q + ' ' + $.trim($(this).closest('.wc-pao-addon-container').find('.dlpqv-chip-t').text()).replace(/\s*\(\+.*\)$/, ''));
      });
      $(this).find('.dlpqv-grp-s').text(resumen.length ? resumen.join(', ') : 'Toca para elegir');
      $ins.each(function () {
        var $w = $(this).closest('.dlpqv-step-wrap');
        $w.find('.dlpqv-step[data-step="1"]').prop('disabled', !libre && sum >= n);
        $w.closest('.dlpqv-chip').toggleClass('on', (parseInt($(this).val(), 10) || 0) > 0);
      });
      if (!libre && sum !== n) wait = true;
    });
    $form.data('dlpGrupoEspera', wait);
    btnSync($form);
  }
  function btnSync($form) {
    var espera = !!($form.data('dlpGrupoEspera') || $form.data('dlpEligeEspera'));
    $form.find('button.single_add_to_cart_button').first().prop('disabled', espera).toggleClass('dlpqv-wait', espera);
  }

  // ---- Elegir entre N y M casillas: [elegir:2] o [elegir:2-3] en la descripción de un extra de casillas ----------
  // Cuenta lo marcado, bloquea las sobrantes al llegar al máximo y no deja añadir hasta llegar al mínimo.
  var ELIGE_RE = /\[elegir:(\d+)(?:\s*-\s*(\d+))?\]/i;
  function choosers($form) {
    $form.find('.wc-pao-addon-container').each(function () {
      var $c = $(this), $d = $c.find('.wc-pao-addon-description').first(), m = ELIGE_RE.exec($d.text());
      if (!m || !$c.find('input.wc-pao-addon-checkbox').length) return;
      var mn = parseInt(m[1], 10), mx = m[2] ? parseInt(m[2], 10) : mn;
      $d.html($d.html().replace(/\[elegir:[^\]]+\]/ig, ''));
      if (!$.trim($d.text())) $d.hide();
      $c.attr('data-dlpqv-min', mn).attr('data-dlpqv-max', mx).addClass('dlpqv-elige');
      $('<div class="dlpqv-choose"></div>').prependTo($c.find('.wc-pao-addon-wrap').first());
    });
    chooseSync($form);
  }
  function chooseSync($form) {
    var espera = false;
    $form.find('.dlpqv-elige').each(function () {
      var $c = $(this), mn = +$c.attr('data-dlpqv-min'), mx = +$c.attr('data-dlpqv-max'), $in = $c.find('input.wc-pao-addon-checkbox'), n = $in.filter(':checked').length;
      $c.find('.dlpqv-choose').text('Elige ' + (mn === mx ? mn : mn + ' a ' + mx) + ' · Marcadas: ' + n).toggleClass('done', n >= mn && n <= mx);
      $in.each(function () { if (!this.checked) $(this).prop('disabled', n >= mx); });
      if (n < mn) espera = true;
    });
    $form.data('dlpEligeEspera', espera);
    btnSync($form);
  }

  // ---- Acordeón para add-ons de casillas: [acordeon:Título] en la descripción --------------------------------
  // Los add-ons consecutivos con el mismo título se envuelven en un solo acordeón cerrado al inicio. Es solo presentación:
  // los campos siguen dentro del formulario (cerrado también se envían) y no se toca ningún precio.
  var ACC_RE = /\[acordeon:([^\]]+)\]/i;
  function accName($c) { return $.trim($c.find('.wc-pao-addon-name').first().clone().children().remove().end().text()); }
  function accSync($form) {
    $form.find('.dlpqv-acc').each(function () {
      var $box = $(this).next('.dlpqv-grp-box'), partes = [], alguno = false;
      $box.children('.wc-pao-addon-container').each(function () {
        var $c = $(this), marcadas = [];
        $c.find('input.wc-pao-addon-checkbox:checked').each(function () {
          var $l = $c.find('label[for="' + this.id + '"]').first();
          if (!$l.length) $l = $(this).siblings('label').first();
          var t = $.trim($l.clone().children().remove().end().text());
          if (t) marcadas.push(t);
        });
        if (marcadas.length) alguno = true;
        partes.push($box.children('.wc-pao-addon-container').length === 1 ? marcadas.join(', ') : accName($c).split(':')[0] + ': ' + (marcadas.length ? marcadas.join(', ') : 'ninguno'));
      });
      $(this).find('.dlpqv-grp-s').text(alguno ? partes.join(' · ') : 'Toca para elegir');
      $(this).toggleClass('done', alguno);
    });
  }
  function accordions($form) {
    var $box = null, title = null;
    $form.find('.wc-pao-addon-container').each(function () {
      var $c = $(this), $d = $c.find('.wc-pao-addon-description').first(), m = ACC_RE.exec($d.text());
      if (!m) { $box = null; title = null; return; }
      var t = $.trim(m[1]);
      $d.html($d.html().replace(/\[acordeon:[^\]]+\]/ig, ''));
      if (!$.trim($d.text())) $d.hide();
      if (!($box && t === title && $c.prev().is($box))) {
        var $h = $('<div class="dlpqv-grp dlpqv-acc" role="button" tabindex="0" aria-expanded="false"><div class="dlpqv-grp-r"><b></b><i class="dlpqv-grp-a" aria-hidden="true"></i></div><div class="dlpqv-grp-s"></div></div>');
        $h.find('b').text(t);
        $h.insertBefore($c);
        $box = $('<div class="dlpqv-grp-box" hidden></div>').insertAfter($h);
        title = t;
      }
      $box.append($c);
    });
    $form.find('.dlpqv-acc').each(function () { var $b = $(this).next('.dlpqv-grp-box'); if ($b.children('.wc-pao-addon-container').length === 1) $b.addClass('dlpqv-grp-box--solo'); });
    accSync($form);
  }

  function enhance() {
    $body.find('img').attr('loading', 'eager');
    var $form = $body.find('form.cart').first();
    if (!$form.length) return;

    // Cantidad con − / +
    var $q = $form.find('.quantity').first();
    var $in = $q.find('input.qty');
    stepper($in, parseFloat($in.attr('min')) || 1, parseFloat($in.attr('max')) || Infinity);
    var $qtyBox = $q.closest('.dlpqv-step-wrap');
    if (!$qtyBox.length) $qtyBox = $q;
    $qtyBox.addClass('dlpqv-qty');

    // Extras numéricos (ej. "Cambio a Angus"): una opción con el texto a la izquierda y − 0 + a la derecha.
    $form.find('input.wc-pao-addon-input-multiplier').each(function () {
      var r = {}; try { r = JSON.parse($(this).attr('data-restrictions') || '{}'); } catch (e) {}
      stepper($(this), r.min != null ? +r.min : 0, r.max != null ? +r.max : Infinity);
      var $c = $(this).closest('.wc-pao-addon-container');
      var $desc = $c.find('.wc-pao-addon-description').first();
      var $price = $c.find('h2.wc-pao-addon-name .wc-pao-addon-price').first();
      var unit = parseFloat($(this).attr('data-price')) || 0;
      var main = $.trim($desc.text()) || 'Cantidad';
      var parts = [];
      // Grupo con total fijo: la descripción trae [grupo:Título=N]; la fila se llama como el extra
      var gm = /\[grupo:([^=\]]+)=(\d+|\*)\]/i.exec(main), group = null;
      if (gm) {
        group = { title: $.trim(gm[1]), n: gm[2] === '*' ? Infinity : parseInt(gm[2], 10) };
        main = $.trim($c.find('h2.wc-pao-addon-name').first().clone().children().remove().end().text());
        if (unit) parts.push('+' + money(unit));
        r.max = group.n;
        $(this).attr('data-dlpqv-group', group.title).attr('data-dlpqv-n', isFinite(group.n) ? group.n : '*');
        $(this).closest('.dlpqv-step-wrap').data('max', group.n);
      } else {
        if (unit && !/c\/u/i.test(main)) parts.push('+' + money(unit) + ' c/u');
        if (isFinite(r.max) && r.max != null && !/hasta/i.test(main)) parts.push('hasta ' + r.max);
      }
      var text = main + (parts.length ? ' (' + parts.join(', ') + ')' : '');
      var $chip = $('<div class="dlpqv-chip"><div class="dlpqv-chip-t"></div></div>');
      $chip.find('.dlpqv-chip-t').text(text);
      $desc.hide();
      $price.hide();
      var $w = $(this).closest('.dlpqv-step-wrap');
      $w.before($chip);
      $chip.append($w);
      $chip.toggleClass('on', parseFloat($(this).val()) > 0);
    });

    // Grupos con total exacto: cabecera con contador "0 de 3"
    var seen = {};
    $form.find('input[data-dlpqv-group]').each(function () {
      var t = $(this).attr('data-dlpqv-group');
      if (seen[t]) return;
      seen[t] = 1;
      var $first = $(this).closest('.wc-pao-addon-container');
      var $h = $('<div class="dlpqv-grp" role="button" tabindex="0" aria-expanded="false" data-g=""><div class="dlpqv-grp-r"><b></b><span class="dlpqv-grp-c"></span><i class="dlpqv-grp-a" aria-hidden="true"></i></div><div class="dlpqv-grp-s"></div></div>')
        .attr('data-g', t).find('b').text(t).end().insertBefore($first);
      // grupo con total exacto = obligatorio: etiqueta roja junto al contador (los grupos libres «=*» son opcionales)
      if (!isNaN(parseInt($(this).attr('data-dlpqv-n'), 10))) $h.find('.dlpqv-grp-c').after('<span class="dlpqv-grp-req">Obligatorio</span>');
      // las filas del grupo van dentro de un contenedor plegable (cerrado al inicio)
      var $box = $('<div class="dlpqv-grp-box" hidden></div>').insertAfter($h);
      groupInputs($form, t).each(function () { $box.append($(this).closest('.wc-pao-addon-container')); });
    });

    accordions($form);
    choosers($form);

    // Pie fijo: cantidad + botón con el total
    var $btn = $form.find('button.single_add_to_cart_button').first();
    var label = $.trim($btn.text()) || 'Añadir al carrito';
    var short = (window.dlpFE && window.dlpFE['home-quickview'] && window.dlpFE['home-quickview'].texto_boton_corto) || 'Añadir';
    var $lbl = $('<span class="dlpqv-lbl"></span>').append($('<span class="dlpqv-lbl-l"></span>').text(label), $('<span class="dlpqv-lbl-s"></span>').text(short));
    $btn.empty().append($lbl, '<span class="dlpqv-tot"></span>');
    $('<div class="dlpqv-foot"></div>').append($qtyBox, $btn).appendTo($form);

    // Inicializa los Add-Ons (validación y totales) en el contenido recién insertado.
    try { $(document.body).trigger('quick-view-displayed'); } catch (e) { if (window.console) console.warn('Add-Ons:', e); }
    var $t = $form.find('#product-addons-total');
    if ($t.length && window.MutationObserver) {
      new MutationObserver(updateTotal).observe($t[0], { childList: true, subtree: true, characterData: true });
    }
    $form.on('change input', 'input, select', function () { setTimeout(updateTotal, 120); });
    $form.on('change input', 'input[data-dlpqv-group]', function () { groupClamp($(this)); groupSync($form); });
    $form.on('change', '.dlpqv-grp-box input.wc-pao-addon-checkbox', function () { accSync($form); });
    $form.on('change', '.dlpqv-elige input.wc-pao-addon-checkbox', function () { chooseSync($form); });
    groupSync($form);
    setTimeout(updateTotal, 250);
    updateTotal();
  }

  /** key: slug o id. fallbackHref: si falla, ir a la página del producto (solo clics desde tarjeta). */
  function open(key, fallbackHref, opts) {
    opts = opts || {};
    build();
    var my = ++token;
    current = String(key);
    $body.html('<div class="dlpqv-loading" aria-live="polite">Cargando…</div>');
    show();
    var data = { action: 'dlp_fe_quickview' };
    if (/^\d+$/.test(current)) data.product_id = current; else data.slug = current;
    $.get(cfg.ajaxurl, data)
      .done(function (r) {
        if (my !== token) return;
        if (!r || !r.success) { failed(fallbackHref); return; }
        $body.html(r.data.html);
        enhance();
        if (!opts.fromHash) setHash(r.data.slug || current);
      })
      .fail(function () { if (my === token) failed(fallbackHref); });
  }

  function failed(href) {
    if (href) { window.location.href = href; return; }
    hide(); // enlace directo a un producto que no existe: se cierra sin ruido
    history.replaceState(null, '', location.pathname + location.search);
  }

  // ---- Eventos ------------------------------------------------------------------------------------------------

  // Clic en un producto del listado (clic normal; con Ctrl/Cmd/medio abre la página como siempre).
  $(document).on('click', 'ul.products li.product a.woocommerce-LoopProduct-link', function (e) {
    if (e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var m = ($(this).closest('li.product').attr('class') || '').match(/\bpost-(\d+)/);
    if (!m) return;
    e.preventDefault();
    var s = (this.href || '').match(/\/producto\/([^\/?#]+)/);
    open(s ? s[1] : m[1], this.href);
  });

  $(document).on('click', '[data-dlpqv-close]', function (e) { e.preventDefault(); close(); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') close(); });

  $(document).on('click', '.dlpqv-step', function () {
    var $w = $(this).closest('.dlpqv-step-wrap'), $in = $w.find('input'), step = parseInt($(this).data('step'), 10);
    var min = $w.data('min'), max = $w.data('max');
    if ($in.attr('data-dlpqv-group') && !isNaN(parseInt($in.attr('data-dlpqv-n'), 10))) {
      var $f = $in.closest('form.cart');
      max = Math.min(max, parseInt($in.attr('data-dlpqv-n'), 10) - groupSum(groupInputs($f, $in.attr('data-dlpqv-group')).not($in)));
    }
    var v = Math.min(max, Math.max(min, (parseFloat($in.val()) || 0) + step));
    $in.val(v).trigger('input').trigger('change');
    $w.closest('.dlpqv-chip').toggleClass('on', v > 0);
  });

  // Acordeón de los grupos: cerrado al inicio; el clic o Enter abre/cierra la lista de opciones
  function grpToggle($g) {
    var open = $g.attr('aria-expanded') !== 'true';
    $g.attr('aria-expanded', open ? 'true' : 'false').toggleClass('open', open);
    $g.next('.dlpqv-grp-box').prop('hidden', !open);
  }
  $(document).on('click', '.dlpqv-grp', function () { grpToggle($(this)); });
  $(document).on('keydown', '.dlpqv-grp', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); grpToggle($(this)); } });

  // Al añadir al carrito, el carrito lateral se abre solo: se cierra el modal.
  $(document.body).on('added_to_cart', close);

  // Enlaces directos y botón Atrás.
  function fromHash() {
    var m = HASH_RE.exec(location.hash);
    if (m) {
      var key = decodeURIComponent(m[1]);
      if (!isOpen() || current !== key) open(key, '', { fromHash: true });
    } else if (isOpen()) {
      pushed = false;
      hide();
    }
  }
  window.addEventListener('hashchange', fromHash);
  $(function () { fromHash(); });
})(jQuery);
