<?php
/**
 * Rendimiento: no carga en páginas sin pago lo que solo sirve para pagar.
 * La huella de dispositivo antifraude (wc-cybersource-device-data / online-metrix) se deja como está a propósito.
 */
if (!defined('ABSPATH')) {
    exit;
}

function dlp_fe_rd_es_pago() {
    if (!function_exists('is_checkout')) {
        return true; // sin WooCommerce no se toca nada
    }
    return is_checkout() || is_account_page() || is_wc_endpoint_url('order-pay') || is_wc_endpoint_url('add-payment-method') || is_admin();
}

function dlp_fe_rd_quitar_pago() {
    if (!DLP_FE_Registry::setting('rendimiento', 'quitar_pago') || dlp_fe_rd_es_pago()) {
        return;
    }
    $scripts = wp_scripts();
    $estilos = wp_styles();
    $es_pago = static function ($h) {
        return $h === 'wc-jquery-payment'
            || strpos($h, 'sv-wc-payment-gateway') === 0
            || (strpos($h, 'wc-cybersource') === 0 && strpos($h, 'device-data') === false);
    };
    foreach (array_keys($scripts->registered) as $h) {
        if ($es_pago($h)) {
            wp_dequeue_script($h);
        }
    }
    foreach (array_keys($estilos->registered) as $h) {
        if ($es_pago($h)) {
            wp_dequeue_style($h);
        }
    }
}
add_action('wp_enqueue_scripts', 'dlp_fe_rd_quitar_pago', 9999);
add_action('wp_print_scripts', 'dlp_fe_rd_quitar_pago', 1);
add_action('wp_print_footer_scripts', 'dlp_fe_rd_quitar_pago', 1);

/** Dashicons: lo usa la barra de administración. Para visitantes se deja vacío (otros estilos lo piden como dependencia y así siguen cargando). */
add_action('wp_enqueue_scripts', function () {
    if (!DLP_FE_Registry::setting('rendimiento', 'quitar_dashicons') || is_user_logged_in()) {
        return;
    }
    wp_deregister_style('dashicons');
    wp_register_style('dashicons', false, [], DLP_FE_VERSION);
}, 9999);

/** Visita anónima que abre una página normal (la que la caché de página puede guardar). */
function dlp_fe_rd_visita_cacheable() {
    if (is_user_logged_in() || (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') || isset($_GET['wc-ajax'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return false;
    }
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    return !preg_match('#/(wp-login|ingresar|mi-cuenta|my-account|finalizar-compra|checkout|carrito|cart)(/|\?|$)#i', $uri);
}

/** User Blocker abre una sesión PHP en cada visita (cookie PHPSESSID + Cache-Control: no-store): la caché de página no puede guardar nada. */
add_action('init', function () {
    if (!DLP_FE_Registry::setting('rendimiento', 'sin_sesion_php') || !dlp_fe_rd_visita_cacheable()) {
        return;
    }
    if (function_exists('ublk_session_start')) {
        remove_action('init', 'ublk_session_start');
    }
}, 1);

/** Al cambiar ajustes que se ven en las páginas, vacía la caché de página de LiteSpeed (si está activa). */
foreach (['dlp_fe_modules', 'dlp_tiendas_settings', 'dlp_fe_checkout_fields', 'dlp_fe_mn_ver'] as $dlp_opt) {
    add_action('update_option_' . $dlp_opt, function () {
        do_action('litespeed_purge_all');
    });
}
