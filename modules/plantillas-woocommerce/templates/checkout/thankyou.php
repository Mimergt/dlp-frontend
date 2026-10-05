<?php
/**
 * Página de gracias minimalista (sin resumen del pedido; lo muestra el resto de la página).
 *
 * Basada en WooCommerce 3.7.0. Revisar contra la versión actual de WooCommerce al actualizar.
 *
 * @version 3.7.0
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="woocommerce-order">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
	<?php endif; ?>
</div>
