jQuery(document).on('click', '.changeStatus', function (e) {
  e.preventDefault();
  var $b = jQuery(this);
  jQuery.post(dlpRecoger.ajaxurl, {
    action: 'change_order_status',
    order_id: $b.data('order-id'),
    order_key: $b.data('order-key')
  }).done(function () {
    alert('Su pedido ha sido notificado. En breve se lo estarán entregando');
    location.reload();
  }).fail(function () {
    alert('No se pudo notificar el pedido. Intenta de nuevo.');
  });
});
