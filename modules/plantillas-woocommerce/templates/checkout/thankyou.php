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

<?php
// Con el módulo gracias-pagina activo, el diseño completo lo imprime él (y dentro llama a woocommerce_thankyou).
if ( function_exists( 'dlp_fe_gr_render' ) ) :
	?>
<div class="woocommerce-order">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>
	<?php endif; ?>
	<?php dlp_fe_gr_render( $order ); ?>
</div>
<?php else : ?>
<div class="woocommerce-order">
	<?php if ( $order ) : ?>
		<?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() ); ?>
		<?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>
	<?php endif; ?>
</div>
<?php endif; ?>
