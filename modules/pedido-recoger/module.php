<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SEGURIDAD: antes el ajax change_order_status era público y aceptaba cualquier order_id.
 * Ahora exige la llave del pedido (order_key), que solo conoce quien hizo el pedido, y solo
 * permite pasar de dlv a rtp en pedidos de recoger.
 */
function dlp_fe_recoger_render($order) {
    if (!$order) {
        return;
    }
    $caja = 'padding:10px;background:#DAD7D8;margin:15px 5px;border-radius:5px;text-transform:initial;text-align:center;';
    if ($order->get_meta('woofood_order_type') === 'pickup' && $order->has_status('dlv')) {
        wp_enqueue_script('dlp-fe-pedido-recoger', DLP_FE_URL . 'modules/pedido-recoger/script.js', ['jquery'], filemtime(__DIR__ . '/script.js'), true);
        wp_localize_script('dlp-fe-pedido-recoger', 'dlpRecoger', ['ajaxurl' => admin_url('admin-ajax.php')]);
        echo '<div style="margin:16px 0 24px;text-align:center;"><a href="#" class="button changeStatus rtp" data-order-id="' . esc_attr($order->get_id()) . '" data-order-key="' . esc_attr($order->get_order_key()) . '">Ya estoy aquí por mi pedido.</a></div>';
    } elseif ($order->has_status('rtp')) {
        echo '<h4 style="' . $caja . '">En breve se estarán comunicando contigo para coordinar la entrega de tu pedido.</h4>';
    } elseif ($order->has_status('completed')) {
        echo '<h4 style="' . $caja . '">Tu pedido se ha completado.</h4>';
    }
}

/** Solo el botón (para páginas con diseño propio, ej. gracias-pagina). */
function dlp_fe_recoger_button($order) {
    wp_enqueue_script('dlp-fe-pedido-recoger', DLP_FE_URL . 'modules/pedido-recoger/script.js', ['jquery'], filemtime(__DIR__ . '/script.js'), true);
    wp_localize_script('dlp-fe-pedido-recoger', 'dlpRecoger', ['ajaxurl' => admin_url('admin-ajax.php')]);
    return '<a href="#" class="button changeStatus rtp dlpgr-btn r" data-order-id="' . esc_attr($order->get_id()) . '" data-order-key="' . esc_attr($order->get_order_key()) . '">Ya estoy aquí por mi pedido</a>';
}

add_action('woocommerce_thankyou', function ($order_id) {
    if (!apply_filters('dlp_fe_recoger_en_gracias', true)) {
        return;
    }
    dlp_fe_recoger_render(wc_get_order($order_id));
}, 5);

add_action('woocommerce_order_details_after_order_table', function ($order) {
    if (is_wc_endpoint_url('view-order')) {
        dlp_fe_recoger_render($order);
    }
});

function dlp_fe_change_order_status() {
    $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $key      = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
    $order    = $order_id ? wc_get_order($order_id) : false;

    if (!$order || $key === '' || !hash_equals($order->get_order_key(), $key)) {
        wp_send_json_error('forbidden', 403);
    }
    if ($order->get_meta('woofood_order_type') !== 'pickup' || !$order->has_status('dlv')) {
        wp_send_json_error('estado no válido', 409);
    }
    $order->update_status('rtp', 'order_note');
    wp_send_json_success();
}
add_action('wp_ajax_change_order_status', 'dlp_fe_change_order_status');
add_action('wp_ajax_nopriv_change_order_status', 'dlp_fe_change_order_status');
