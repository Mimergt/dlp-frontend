<?php
/**
 * Detalle de un pedido en Mi cuenta: encabezado con estado y datos clave, y debajo el detalle estándar de WooCommerce.
 *
 * Basada en WooCommerce 10.6.0. Revisar contra la versión actual al actualizar.
 *
 * @version 10.6.0
 */

defined( 'ABSPATH' ) || exit;

$notes   = $order->get_customer_order_notes();
$status  = $order->get_status();
$pickup  = $order->get_meta( 'woofood_order_type' ) === 'pickup';
$tienda  = (string) $order->get_meta( 'tienda_asignada' );
$cls     = in_array( $status, array( 'completed' ), true ) ? 'ok' : ( in_array( $status, array( 'cancelled', 'failed', 'refunded' ), true ) ? 'no' : '' );
?>
<div class="dlpx-vo">
	<div class="dlpx-vo-top">
		<div>
			<div class="dlpx-vo-n">Pedido #<?php echo esc_html( $order->get_order_number() ); ?></div>
			<div class="dlpx-vo-d"><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'j M Y · g:i a' ) ); ?></div>
		</div>
		<span class="dlpx-chip <?php echo esc_attr( $cls ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
	</div>
	<div class="dlpx-vo-meta">
		<span><?php echo $pickup ? 'Para recoger' : 'A domicilio'; ?></span>
		<?php if ( $tienda !== '' ) : ?><span><?php echo esc_html( $tienda ); ?></span><?php endif; ?>
		<span><?php echo esc_html( $order->get_payment_method_title() ); ?></span>
	</div>
</div>

<?php if ( $notes ) : ?>
	<ol class="woocommerce-OrderUpdates commentlist notes dlpx-notes">
		<?php foreach ( $notes as $note ) : ?>
		<li class="woocommerce-OrderUpdate comment note">
			<div class="woocommerce-OrderUpdate-inner comment_container">
				<div class="woocommerce-OrderUpdate-text comment-text">
					<p class="woocommerce-OrderUpdate-meta meta"><?php echo esc_html( date_i18n( 'j M Y · g:i a', strtotime( $note->comment_date ) ) ); ?></p>
					<div class="woocommerce-OrderUpdate-description description">
						<?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?>
					</div>
				</div>
			</div>
		</li>
		<?php endforeach; ?>
	</ol>
<?php endif; ?>

<?php do_action( 'woocommerce_view_order', $order_id ); ?>
