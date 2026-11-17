<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout: cuenta automática. La cuenta se crea con el correo del pedido (lo decide checkout-campos: createaccount = 1 si el
 * correo no existe). Aquí la contraseña pasa a ser opcional: vacía, WooCommerce genera una y envía el correo para crearla.
 */
add_filter('woocommerce_checkout_fields', function ($f) {
    if (isset($f['account']['account_password'])) {
        $f['account']['account_password']['required']    = false;
        $f['account']['account_password']['placeholder'] = 'Opcional';
        $f['account']['account_password']['label']       = (string) DLP_FE_Registry::setting('checkout-tus-datos', 'etiqueta', 'Contraseña (opcional)');
    }
    return $f;
}, 99999);

// El medidor de fuerza de WooCommerce puede bloquear «Realizar el pedido»; aquí solo se exige el largo mínimo si escriben algo.
add_action('wp_enqueue_scripts', function () {
    if (function_exists('is_checkout') && is_checkout()) {
        wp_dequeue_script('wc-password-strength-meter');
    }
}, 99);
add_action('wp_footer', function () {
    if (function_exists('is_checkout') && is_checkout()) {
        wp_dequeue_script('wc-password-strength-meter');
    }
}, 1);

add_action('wp_enqueue_scripts', function () {
    if (wp_script_is('dlp-fe-checkout-tus-datos', 'enqueued')) {
        wp_localize_script('dlp-fe-checkout-tus-datos', 'dlpTD', ['ajaxurl' => admin_url('admin-ajax.php')]);
    }
}, 20);

/** ¿Ya existe una cuenta con este correo? Solo responde sí/no y limita las consultas por IP. */
function dlp_fe_td_email_existe() {
    $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x');
    $k  = 'dlp_fe_td_' . md5((string) $ip);
    $n  = (int) get_transient($k);
    if ($n >= 30) {
        wp_send_json(['exists' => false, 'limited' => true]);
    }
    set_transient($k, $n + 1, 10 * MINUTE_IN_SECONDS);
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    wp_send_json(['exists' => ($email !== '' && is_email($email) && (bool) get_user_by('email', $email))]);
}
add_action('wp_ajax_nopriv_dlp_fe_email_existe', 'dlp_fe_td_email_existe');
add_action('wp_ajax_dlp_fe_email_existe', 'dlp_fe_td_email_existe');
