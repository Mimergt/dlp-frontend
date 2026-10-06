<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menú inferior para celular: Menú, Cuenta y Carrito.
 * El carrito no tiene lógica propia: el script le pasa el clic al botón flotante del carrito lateral (Xootix) para
 * abrir exactamente el mismo panel, y copia la cantidad de su contador.
 */
add_action('wp_footer', function () {
    if (!function_exists('WC') || is_checkout() || is_order_received_page()) {
        return;
    }
    $o = function ($k) {
        return DLP_FE_Registry::setting('menu-inferior', $k);
    };
    $menu_url = $o('url_menu') ?: home_url('/');
    $count    = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
    $account  = wc_get_page_permalink('myaccount');
    $is_acct  = is_account_page();
    $is_home  = is_front_page();
    $icons    = [
        'menu' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9.5h12V10"/><path d="M10 19.5v-5h4v5"/></svg>',
        'user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.2" r="3.7"/><path d="M4.8 20c.6-3.7 3.6-5.8 7.2-5.8s6.6 2.1 7.2 5.8"/></svg>',
        'cart' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2.4l2.2 10.2h10.1L19.6 7H6.3"/><circle cx="9.3" cy="18.6" r="1.4"/><circle cx="17" cy="18.6" r="1.4"/></svg>',
    ];
    ?>
    <nav class="dlpmi" aria-label="Menú inferior">
        <a class="dlpmi-it<?php echo $is_home ? ' is-on' : ''; ?>" href="<?php echo esc_url($menu_url); ?>" data-dlpmi="menu">
            <?php echo $icons['menu']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span><?php echo esc_html($o('texto_menu')); ?></span>
        </a>
        <a class="dlpmi-it<?php echo $is_acct ? ' is-on' : ''; ?>" href="<?php echo esc_url($account); ?>">
            <?php echo $icons['user']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span><?php echo esc_html($o('texto_cuenta')); ?></span>
        </a>
        <button type="button" class="dlpmi-it" data-dlpmi="cart">
            <?php echo $icons['cart']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <span><?php echo esc_html($o('texto_carrito')); ?></span>
            <i class="dlpmi-bd"<?php echo $count ? '' : ' hidden'; ?>><?php echo (int) $count; ?></i>
        </button>
    </nav>
    <?php
}, 20);
