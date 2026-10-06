<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Libreta de direcciones propia. Cada dirección: id, nombre (Casa, Trabajo…), texto, referencia y punto (lat/lng).
 * Se guarda en el meta de usuario dlp_fe_addresses. La cobertura y el horario los resuelve dlp-tiendas
 * (dlp_tiendas_check_point); el servidor sigue revalidando todo al hacer el pedido.
 */
const DLP_FE_LIB_META = 'dlp_fe_addresses';

function dlp_fe_lib_get($uid) {
    $a = get_user_meta($uid, DLP_FE_LIB_META, true);
    return is_array($a) ? array_values($a) : [];
}

function dlp_fe_lib_status($a) {
    $st = ['ok' => true, 'msg' => '', 'store' => ''];
    if (function_exists('dlp_tiendas_check_point')) {
        $r = dlp_tiendas_check_point($a['lat'], $a['lng']);
        $st['ok']    = !empty($r['ok']);
        $st['msg']   = isset($r['msg']) ? $r['msg'] : '';
        $st['store'] = !empty($r['store']) ? get_the_title((int) $r['store']) : '';
    }
    return $st;
}

add_action('wp_enqueue_scripts', function () {
    if (!wp_script_is('dlp-fe-checkout-libreta', 'enqueued')) {
        return;
    }
    $list = [];
    if (is_user_logged_in()) {
        foreach (dlp_fe_lib_get(get_current_user_id()) as $a) {
            $list[] = array_merge($a, dlp_fe_lib_status($a));
        }
    }
    wp_localize_script('dlp-fe-checkout-libreta', 'dlpLib', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('dlp_fe_lib'),
        'logged'  => is_user_logged_in(),
        'max'     => (int) DLP_FE_Registry::setting('checkout-libreta', 'max'),
        'list'    => $list,
    ]);
}, 20);

// Guarda la dirección al hacer el pedido (si el cliente lo pidió y el punto es válido).
add_action('woocommerce_checkout_order_processed', function ($order_id, $posted, $order) {
    $uid = get_current_user_id();
    if (!$uid || empty($_POST['dlp_save_address'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce ya validó el nonce del checkout
        return;
    }
    $name = isset($_POST['dlp_address_name']) ? sanitize_text_field(wp_unslash($_POST['dlp_address_name'])) : '';
    $lat  = isset($_POST['billing_dlp_lat']) ? wc_clean(wp_unslash($_POST['billing_dlp_lat'])) : '';
    $lng  = isset($_POST['billing_dlp_lng']) ? wc_clean(wp_unslash($_POST['billing_dlp_lng'])) : '';
    if ($name === '' || !is_numeric($lat) || !is_numeric($lng) || abs($lat) > 90 || abs($lng) > 180) {
        return;
    }
    $new = [
        'id'      => substr(md5(uniqid('', true)), 0, 10),
        'name'    => mb_substr($name, 0, 40),
        'address' => isset($_POST['billing_address_2']) ? sanitize_text_field(wp_unslash($_POST['billing_address_2'])) : '',
        'ref'     => isset($_POST['billing_address_name']) ? sanitize_text_field(wp_unslash($_POST['billing_address_name'])) : '',
        'lat'     => round((float) $lat, 7),
        'lng'     => round((float) $lng, 7),
    ];
    $list = dlp_fe_lib_get($uid);
    foreach ($list as $i => $a) { // mismo punto (≈ 10 m): se actualiza en vez de duplicar
        if (abs($a['lat'] - $new['lat']) < 0.0001 && abs($a['lng'] - $new['lng']) < 0.0001) {
            $new['id'] = $a['id'];
            $list[$i]  = $new;
            update_user_meta($uid, DLP_FE_LIB_META, array_values($list));
            return;
        }
    }
    $max = max(1, (int) DLP_FE_Registry::setting('checkout-libreta', 'max'));
    if (count($list) >= $max) {
        return; // libreta llena: el cliente debe eliminar una para guardar otra
    }
    $list[] = $new;
    update_user_meta($uid, DLP_FE_LIB_META, array_values($list));
}, 10, 3);

add_action('wp_ajax_dlp_fe_lib_delete', function () {
    check_ajax_referer('dlp_fe_lib', 'nonce');
    $uid = get_current_user_id();
    $id  = isset($_POST['id']) ? sanitize_key(wp_unslash($_POST['id'])) : '';
    if (!$uid || $id === '') {
        wp_send_json_error();
    }
    $list = array_values(array_filter(dlp_fe_lib_get($uid), function ($a) use ($id) {
        return $a['id'] !== $id;
    }));
    update_user_meta($uid, DLP_FE_LIB_META, $list);
    wp_send_json_success();
});
