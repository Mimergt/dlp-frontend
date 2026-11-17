<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Recuperar contraseña con diseño propio. Reemplaza las plantillas de WooCommerce (myaccount/form-lost-password,
 * lost-password-confirmation y form-reset-password); la lógica, los campos y los nonces siguen siendo los de WooCommerce.
 */
function dlp_fe_rc_is_lost() {
    return function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('lost-password');
}

add_filter('body_class', function ($c) {
    if (dlp_fe_rc_is_lost()) {
        $c[] = 'dlprc-page';
    }
    return $c;
});

add_filter('woocommerce_locate_template', function ($template, $name) {
    $map = ['myaccount/form-lost-password.php', 'myaccount/lost-password-confirmation.php', 'myaccount/form-reset-password.php'];
    if (in_array($name, $map, true)) {
        $f = __DIR__ . '/plantillas/' . basename($name);
        if (is_readable($f)) {
            return $f;
        }
    }
    return $template;
}, 20, 2);

// El medidor de fuerza de WooCommerce deshabilita el botón: se quita (se encola al pintar la página, de ahí el pie).
add_action('wp_footer', function () {
    if (dlp_fe_rc_is_lost()) {
        wp_dequeue_script('wc-password-strength-meter');
    }
}, 1);
add_action('wp_enqueue_scripts', function () {
    if (dlp_fe_rc_is_lost()) {
        wp_dequeue_script('wc-password-strength-meter'); // el largo mínimo se pide con texto claro, sin bloquear el botón
        wp_localize_script('dlp-fe-recuperar-contrasena', 'dlpRC', ['min' => (int) DLP_FE_Registry::setting('recuperar-contrasena', 'min', 8)]);
    }
}, 99);

/** Pieza común: ojo para ver la contraseña. */
function dlp_fe_rc_pw($id, $name, $label, $auto = 'new-password') {
    echo '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>';
    echo '<span class="dlprc-pw"><input type="password" name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" autocomplete="' . esc_attr($auto) . '" required aria-required="true">';
    echo '<button type="button" class="dlprc-eye" aria-label="Mostrar contraseña">Ver</button></span>';
}

function dlp_fe_rc_login_url() {
    $slug = trim((string) DLP_FE_Registry::setting('login-pagina', 'slug', 'ingresar'), '/');
    return home_url('/' . ($slug ?: 'ingresar') . '/');
}

// Avisos de WooCommerce con palabras más claras (solo en estas pantallas).
add_filter('woocommerce_add_error', function ($msg) {
    // El error del formulario se crea en wp_loaded, antes de saber el endpoint: también se reconoce por el envío del formulario.
    if (!dlp_fe_rc_is_lost() && empty($_POST['wc_reset_password'])) { // phpcs:ignore WordPress.Security.NonceVerification
        return $msg;
    }
    $t = wp_strip_all_tags((string) $msg);
    if (stripos($t, 'clave no es válida') !== false) {
        return 'Este enlace no es válido o ya se usó. Pide uno nuevo con tu correo.';
    }
    if (stripos($t, 'no válido') !== false && stripos($t, 'correo') !== false) {
        return 'No encontramos una cuenta con ese correo. Revísalo e inténtalo de nuevo.';
    }
    return $msg;
});
