<?php
if (!defined('ABSPATH')) {
    exit;
}

function dlp_fe_px_lost() {
    return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('lost-password');
}

add_filter('body_class', function ($c) {
    if (dlp_fe_px_lost()) {
        $c[] = 'dlpx-lost';
    }
    if (is_404()) {
        $c[] = 'dlpx-404';
    }
    if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('view-order')) {
        $c[] = 'dlpx-vieworder';
    }
    return $c;
});

// Recuperar / restablecer contraseña: encabezado propio
add_action('woocommerce_before_lost_password_form', function () {
    echo '<div class="dlpx-hero"><h1>¿Olvidaste tu contraseña?</h1><p>Escribe tu correo y te enviamos un enlace para crear una nueva.</p></div>';
});
add_action('woocommerce_before_reset_password_form', function () {
    echo '<div class="dlpx-hero"><h1>Crea tu nueva contraseña</h1><p>Elige una contraseña que recuerdes.</p></div>';
});
add_action('woocommerce_before_lost_password_confirmation_message', function () {
    echo '<div class="dlpx-hero"><h1>Revisa tu correo</h1></div>';
});

// 404 propia (usa el header y footer de Divi)
add_filter('template_include', function ($tpl) {
    if (is_404() && is_readable(__DIR__ . '/404.php')) {
        return __DIR__ . '/404.php';
    }
    return $tpl;
}, 99);

// Producto y categorías llevan al inicio (el menú vive ahí). ?dlp_native=1 las deja ver tal cual.
add_action('template_redirect', function () {
    if (is_admin() || !function_exists('is_product') || (isset($_GET['dlp_native']) && $_GET['dlp_native']) || (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] !== 'GET')) {
        return;
    }
    if (isset($_GET['add-to-cart']) || isset($_GET['wc-ajax'])) {
        return;
    }
    if (is_product() && DLP_FE_Registry::setting('paginas-extra', 'redir_producto')) {
        $p = get_queried_object();
        if ($p && !empty($p->post_name)) {
            wp_safe_redirect(home_url('/#producto-' . $p->post_name), 301);
            exit;
        }
    }
    if ((is_product_category() || is_product_tag() || is_shop()) && DLP_FE_Registry::setting('paginas-extra', 'redir_categoria')) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
}, 6);
