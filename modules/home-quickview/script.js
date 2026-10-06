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
    return sym + (Math.round(n * 100) / 100).toFixed(2);
  }

  // Total en vivo en el botón: lo calculan los Add-Ons (Subtotal); sin extras seleccionados, precio × cantidad.
  function updateTotal() {
    var $t = $body.find('#product-addons-total');
    var txt = '';
    var $sub = $t.find('.wc-pao-subtotal-line .amount').last();
    if ($sub.length) txt = $.trim($sub.text());
    if (!txt) {
      var base = parseFloat($t.attr('data-price'));
      if (isNaN(base)) base = parseFloat($body.find('.dlpqv-hero').attr('data-price'));
      var qty = parseFloat($body.find('input.qty').val()) || 1;
      if (!isNaN(base)) txt = money(base * qty);
    }
    if (txt) $body.find('.dlpqv-tot').text(txt);
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

    // Extras numéricos (ej. "Cambio a Angus") también como − / +
    $form.find('input.wc-pao-addon-input-multiplier').each(function () {
      var r = {}; try { r = JSON.parse($(this).attr('data-restrictions') || '{}'); } catch (e) {}
      stepper($(this), r.min != null ? +r.min : 0, r.max != null ? +r.max : Infinity);
    });

    // Pie fijo: cantidad + botón con el total
    var $btn = $form.find('button.single_add_to_cart_button').first();
    var label = $.trim($btn.text()) || 'Añadir al carrito';
    $btn.empty().append($('<span class="dlpqv-lbl"></span>').text(label), '<span class="dlpqv-tot"></span>');
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
