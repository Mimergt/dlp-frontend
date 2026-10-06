<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Splash / transición entre páginas (solo celular por CSS). La capa se imprime al inicio del <body> para cubrir la
 * página desde el primer pintado; el script la quita cuando carga (y el CSS la quita solo a los 4 s por seguridad).
 */
add_action('wp_body_open', function () {
    if (is_admin() || wp_doing_ajax() || is_feed() || is_embed()) {
        return;
    }
    $logo = DLP_FE_Registry::setting('splash-transicion', 'logo');
    if (is_numeric($logo)) {
        $logo = wp_get_attachment_image_url((int) $logo, 'full');
    }
    if (!$logo) {
        $divi = get_option('et_divi');
        $logo = is_array($divi) && !empty($divi['divi_logo']) ? $divi['divi_logo'] : '';
    }
    $texto = DLP_FE_Registry::setting('splash-transicion', 'texto');
    ?>
    <div class="dlpta is-on" aria-hidden="true">
        <div class="dlpta-lg">
            <?php if ($logo) : ?>
                <img src="<?php echo esc_url($logo); ?>" alt="" decoding="async">
            <?php else : ?>
                <b><?php echo esc_html(get_bloginfo('name')); ?></b>
            <?php endif; ?>
            <?php if ($texto) : ?><em><?php echo esc_html($texto); ?></em><?php endif; ?>
            <small></small>
        </div>
    </div>
    <?php
}, 1);
