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
      if (unit && !/c\/u/i.test(main)) parts.push('+' + money(unit) + ' c/u');
      if (isFinite(r.max) && r.max != null && !/hasta/i.test(main)) parts.push('hasta ' + r.max);
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
    var v = Math.min(max, Math.max(min, (parseFloat($in.val()) || 0) + step));
    $in.val(v).trigger('input').trigger('change');
    $w.closest('.dlpqv-chip').toggleClass('on', v > 0);
  });

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
