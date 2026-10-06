<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Página de ingreso propia. Se pinta sobre la página existente (por defecto /ingresar/): se conserva la URL, los
 * enlaces y las redirecciones; el contenido de Divi de esa página ya no se muestra.
 * Entrada por Ajax con wp_signon (acepta correo o usuario) y límite de intentos por IP y por cuenta.
 */
function dlp_fe_lg_is_page() {
    $slug = trim((string) DLP_FE_Registry::setting('login-pagina', 'slug'), '/');
    return $slug !== '' && !is_admin() && function_exists('is_page') && is_page($slug);
}

// Ya con sesión: directo a Mi cuenta
add_action('template_redirect', function () {
    if (dlp_fe_lg_is_page() && is_user_logged_in()) {
        $to = isset($_GET['redirect_to']) ? wp_validate_redirect(wp_unslash($_GET['redirect_to']), '') : '';
        wp_safe_redirect($to ?: (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/')));
        exit;
    }
});

add_filter('body_class', function ($c) {
    if (dlp_fe_lg_is_page()) {
        $c[] = 'dlplg-page';
    }
    return $c;
});

add_filter('the_content', function ($content) {
    if (!dlp_fe_lg_is_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $o     = function ($k) { return DLP_FE_Registry::setting('login-pagina', $k); };
    $guest = home_url($o('url_invitado') ?: '/');
    $lost  = function_exists('wc_get_endpoint_url') ? wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount')) : wp_lostpassword_url(); // directo a WooCommerce (Theme My Login reescribe wc_lostpassword_url)
    $to    = isset($_GET['redirect_to']) ? wp_validate_redirect(wp_unslash($_GET['redirect_to']), '') : '';
    ob_start();
    ?>
    <div class="dlplg" data-redirect="<?php echo esc_attr($to); ?>">
        <div class="dlplg-hero"><h1><?php echo esc_html($o('titulo')); ?></h1><p><?php echo esc_html($o('texto')); ?></p></div>
        <form class="dlplg-form" novalidate>
            <div class="dlplg-msg" role="alert" aria-live="polite" hidden></div>
            <label>Correo electrónico<input type="email" name="log" autocomplete="username" inputmode="email" required></label>
            <label>Contraseña<span class="dlplg-pw"><input type="password" name="pwd" autocomplete="current-password" required><button type="button" class="dlplg-eye" aria-label="Mostrar contraseña">Ver</button></span></label>
            <div class="dlplg-row"><label class="dlplg-chk"><input type="checkbox" name="rememberme" value="1"> Recordarme</label><a href="<?php echo esc_url($lost); ?>">¿Olvidaste tu contraseña?</a></div>
            <button type="submit" class="dlplg-go">Ingresar</button>
            <div class="dlplg-or"><span>o</span></div>
            <a class="dlplg-guest" href="<?php echo esc_url($guest); ?>"><?php echo esc_html($o('texto_invitado')); ?></a>
        </form>
    </div>
    <?php
    return ob_get_clean();
}, 99);

add_action('wp_enqueue_scripts', function () {
    if (dlp_fe_lg_is_page() && wp_script_is('dlp-fe-login-pagina', 'enqueued')) {
        wp_localize_script('dlp-fe-login-pagina', 'dlpLg', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'home'    => function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/'),
        ]);
    }
}, 20);

function dlp_fe_lg_ip() {
    $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'x';
}

function dlp_fe_lg_hit($key, $limit) {
    $k = 'dlp_fe_lg_' . md5($key);
    $n = (int) get_transient($k);
    if ($n >= $limit) {
        return false;
    }
    set_transient($k, $n + 1, 15 * MINUTE_IN_SECONDS);
    return true;
}

function dlp_fe_lg_ajax() {
    $log = isset($_POST['log']) ? trim(sanitize_text_field(wp_unslash($_POST['log']))) : '';
    $pwd = isset($_POST['pwd']) ? (string) wp_unslash($_POST['pwd']) : '';
    if ($log === '' || $pwd === '') {
        wp_send_json_error(['msg' => 'Escribe tu correo y tu contraseña.']);
    }
    if (!dlp_fe_lg_hit('ip:' . dlp_fe_lg_ip(), 20) || !dlp_fe_lg_hit('u:' . strtolower($log), 10)) {
        wp_send_json_error(['msg' => 'Demasiados intentos. Espera unos minutos e intenta de nuevo.']);
    }
    $user = wp_signon(['user_login' => $log, 'user_password' => $pwd, 'remember' => !empty($_POST['rememberme'])], is_ssl());
    if (is_wp_error($user)) {
        wp_send_json_error(['msg' => 'Correo o contraseña incorrectos.']);
    }
    $to = isset($_POST['redirect_to']) ? wp_validate_redirect(esc_url_raw(wp_unslash($_POST['redirect_to'])), '') : '';
    wp_send_json_success(['to' => $to ?: (function_exists('wc_get_page_permalink') ? wc_get_page_permalink('myaccount') : home_url('/'))]);
}
add_action('wp_ajax_nopriv_dlp_fe_login', 'dlp_fe_lg_ajax');
add_action('wp_ajax_dlp_fe_login', 'dlp_fe_lg_ajax');
