<?php
if (!defined('ABSPATH')) {
    exit;
}

// Tiempo total (HH:MM) entre creación y completado.
add_action('woocommerce_order_status_changed', function ($order_id, $from, $to) {
    if ($to !== 'completed') {
        return;
    }
    $order = wc_get_order($order_id);
    if (!$order || !$order->get_date_created() || !$order->get_date_completed()) {
        return;
    }
    $dif = gmdate('H:i', $order->get_date_completed()->getTimestamp() - $order->get_date_created()->getTimestamp());
    update_post_meta($order_id, 'tiempo_total', $dif);
}, 10, 3);

// Dispositivo, según la landing page que guarda HandL UTM Grabber.
add_action('woocommerce_checkout_update_order_meta', function ($order_id) {
    $url = (string) get_post_meta($order_id, 'handl_landing_page', true);
    if (strpos($url, 'Android') !== false) {
        $device = 'Android';
    } elseif (strpos($url, 'IOS') !== false) {
        $device = 'IOS';
    } else {
        $device = 'MWeb';
    }
    update_post_meta($order_id, 'device', $device);
});

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    if ($order->get_meta('device')) {
        echo '<p><b>Device:</b><br/>' . esc_html($order->get_meta('device')) . '</p>';
    }
});
