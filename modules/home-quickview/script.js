/* Producto en modal (vista rápida). Ver module.php. */
(function ($) {
  'use strict';
  var cfg = window.dlpQV || {};
  var $modal, $body, lastFocus = null, token = 0;

  function build() {
    if ($modal) return;
    $modal = $(
      '<div class="dlpqv" aria-hidden="true">' +
        '<div class="dlpqv-overlay" data-dlpqv-close></div>' +
        '<div class="dlpqv-dialog" role="dialog" aria-modal="true" aria-labelledby="dlpqv-title" tabindex="-1">' +
          '<button type="button" class="dlpqv-close" data-dlpqv-close aria-label="Cerrar">&times;</button>' +
          '<div class="dlpqv-body"></div>' +
        '</div>' +
      '</div>'
    ).appendTo(document.body);
    $body = $modal.find('.dlpqv-body');
  }

  function show() {
    lastFocus = document.activeElement;
    $modal.addClass('is-open').attr('aria-hidden', 'false');
    $('html').addClass('dlpqv-lock');
    $modal.find('.dlpqv-dialog').trigger('focus');
  }

  function close() {
    if (!$modal || !$modal.hasClass('is-open')) return;
    token++;
    $modal.removeClass('is-open').attr('aria-hidden', 'true');
    $('html').removeClass('dlpqv-lock');
    $body.empty();
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  // Botones − / + junto a la cantidad.
  function enhance() {
    $body.find('.quantity').each(function () {
      var $q = $(this), $in = $q.find('input.qty');
      if (!$in.length || $q.parent().hasClass('dlpqv-qty')) return;
      $q.wrap('<div class="dlpqv-qty"></div>');
      $('<button type="button" class="dlpqv-step" data-step="-1" aria-label="Menos">−</button>').insertBefore($q);
      $('<button type="button" class="dlpqv-step" data-step="1" aria-label="Más">+</button>').insertAfter($q);
    });
  }

  function open(id, href) {
    build();
    var my = ++token;
    $body.html('<div class="dlpqv-loading" aria-live="polite">Cargando…</div>');
    show();
    $.get(cfg.ajaxurl, { action: 'dlp_fe_quickview', product_id: id })
      .done(function (r) {
        if (my !== token) return;
        if (!r || !r.success) { window.location.href = href; return; }
        $body.html(r.data.html);
        enhance();
        // Inicializa los Add-Ons (calculan el total y validan) en el contenido recién insertado.
        $(document.body).trigger('quick-view-displayed');
        $body.find('.dlpqv-title').attr('tabindex', '-1');
      })
      .fail(function () { if (my === token) window.location.href = href; });
  }

  // Click en un producto del listado (clic normal; con Ctrl/Cmd/medio abre la página como siempre).
  $(document).on('click', 'ul.products li.product a.woocommerce-LoopProduct-link', function (e) {
    if (e.which > 1 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var m = ($(this).closest('li.product').attr('class') || '').match(/\bpost-(\d+)/);
    if (!m) return;
    e.preventDefault();
    open(m[1], this.href);
  });

  $(document).on('click', '[data-dlpqv-close]', function (e) { e.preventDefault(); close(); });
  $(document).on('keydown', function (e) { if (e.key === 'Escape') close(); });

  $(document).on('click', '.dlpqv-step', function () {
    var $in = $(this).closest('.dlpqv-qty').find('input.qty'), step = parseInt($(this).data('step'), 10);
    var min = parseFloat($in.attr('min')) || 1, max = parseFloat($in.attr('max')) || Infinity;
    var v = Math.min(max, Math.max(min, (parseFloat($in.val()) || min) + step));
    $in.val(v).trigger('change');
  });

  // Al añadir al carrito, el carrito lateral se abre solo: se cierra el modal.
  $(document.body).on('added_to_cart', close);
})(jQuery);
