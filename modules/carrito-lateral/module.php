<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * La página /carrito/ de WooCommerce se reemplaza por el carrito lateral: se manda al inicio y el script abre el panel.
 * Quien llegue por un enlace viejo al carrito ve el mismo carrito de siempre, en el panel.
 */
add_action('template_redirect', function () {
    if (!DLP_FE_Registry::setting('carrito-lateral', 'carrito_a_lateral') || !function_exists('is_cart') || !is_cart()) {
        return;
    }
    wp_safe_redirect(home_url('/#carrito'));
    exit;
}, 5);
