<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
 * El tema sobrescribía form-checkout.php solo para sacar el hook woocommerce_checkout_before_order_review
 * (donde dlp-tiendas pinta el selector de entrega) a la parte de arriba, dentro de <div class="selec-del">.
 * Aquí se hace con hooks: se dispara arriba y se vacía para que no se repita en su lugar normal.
 */
add_action('woocommerce_checkout_before_customer_details', function () {
    if (!has_action('woocommerce_checkout_before_order_review')) {
        return;
    }
    echo '<div class="selec-del">';
    do_action('woocommerce_checkout_before_order_review');
    echo '</div>';
    remove_all_actions('woocommerce_checkout_before_order_review');
}, 5);

// Métodos de pago debajo de "notas del pedido".
// CSS necesario (va en el módulo base): body .woocommerce-checkout-payment { float: none; width: 100%; }
remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
add_action('woocommerce_after_order_notes', 'woocommerce_checkout_payment', 20);
