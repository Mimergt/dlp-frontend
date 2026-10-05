<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Vista rápida de producto en modal. Reemplaza el quickview de WooFood.
 *
 * - Ajax público dlp_fe_quickview?product_id=ID → HTML del producto (imagen, precio, descripción corta y el
 *   formulario de WooCommerce con los Add-Ons). Solo productos publicados y visibles.
 * - El envío al carrito lo hace el carrito lateral (Xootix) por ajax, igual que en la página del producto.
 * - Comentarios: se guardan en el ítem del carrito y se copian al pedido.
 */
function dlp_fe_qv_opt($key) {
    return DLP_FE_Registry::setting('home-quickview', $key);
}

function dlp_fe_quickview() {
    $id      = isset($_REQUEST['product_id']) ? absint($_REQUEST['product_id']) : 0;
    $product = $id ? wc_get_product($id) : false;
    if (!$product || $product->get_status() !== 'publish' || !$product->is_visible()) {
        wp_send_json_error('no disponible', 404);
    }

    global $post;
    $post = get_post($id); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
    setup_postdata($post);
    $GLOBALS['product'] = $product;

    $GLOBALS['dlp_fe_qv_rendering'] = true;
    ob_start();
    ?>
    <div class="dlpqv-grid">
        <div class="dlpqv-media"><?php echo $product->get_image('woocommerce_single'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
        <div class="dlpqv-info">
            <h2 class="dlpqv-title" id="dlpqv-title"><?php echo esc_html($product->get_name()); ?></h2>
            <div class="dlpqv-price price"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php if ($product->get_short_description()) : ?>
                <div class="dlpqv-desc"><?php echo wp_kses_post(wpautop($product->get_short_description())); ?></div>
            <?php endif; ?>
            <?php woocommerce_template_single_add_to_cart(); ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();
    unset($GLOBALS['dlp_fe_qv_rendering']);
    wp_reset_postdata();

    wp_send_json_success([
        'html'  => $html,
        'title' => $product->get_name(),
        'url'   => get_permalink($id),
    ]);
}
add_action('wp_ajax_dlp_fe_quickview', 'dlp_fe_quickview');
add_action('wp_ajax_nopriv_dlp_fe_quickview', 'dlp_fe_quickview');

// Caja de comentarios dentro del formulario (solo cuando se dibuja en el modal).
add_action('woocommerce_before_add_to_cart_button', function () {
    if (empty($GLOBALS['dlp_fe_qv_rendering']) || !dlp_fe_qv_opt('comentarios')) {
        return;
    }
    echo '<textarea name="dlp_nota" class="dlpqv-nota" rows="3" maxlength="300" placeholder="' . esc_attr(dlp_fe_qv_opt('texto_comentarios')) . '"></textarea>';
}, 5);

// El texto del botón.
add_filter('woocommerce_product_single_add_to_cart_text', function ($text) {
    if (!empty($GLOBALS['dlp_fe_qv_rendering']) && dlp_fe_qv_opt('texto_boton')) {
        return dlp_fe_qv_opt('texto_boton');
    }
    return $text;
});

// Comentarios → carrito → pedido.
add_filter('woocommerce_add_cart_item_data', function ($data) {
    if (!empty($_POST['dlp_nota'])) {
        $nota = trim(sanitize_textarea_field(wp_unslash($_POST['dlp_nota'])));
        if ($nota !== '') {
            $data['dlp_nota']   = mb_substr($nota, 0, 300);
            $data['unique_key'] = md5($nota . microtime()); // dos pedidos con distinta nota no se fusionan
        }
    }
    return $data;
});
add_filter('woocommerce_get_item_data', function ($item_data, $cart_item) {
    if (!empty($cart_item['dlp_nota'])) {
        $item_data[] = ['key' => 'Comentarios', 'value' => $cart_item['dlp_nota']];
    }
    return $item_data;
}, 10, 2);
add_action('woocommerce_checkout_create_order_line_item', function ($item, $cart_item_key, $values) {
    if (!empty($values['dlp_nota'])) {
        $item->add_meta_data('Comentarios', $values['dlp_nota']);
    }
}, 10, 3);

// Estilos de los Add-Ons y datos para el JS (el plugin solo los carga en la página del producto).
add_action('wp_enqueue_scripts', function () {
    if (!wp_script_is('dlp-fe-home-quickview', 'enqueued')) {
        return;
    }
    if (defined('WC_PRODUCT_ADDONS_PLUGIN_URL') && defined('WC_PRODUCT_ADDONS_VERSION')) {
        wp_enqueue_style('woocommerce-addons-css', WC_PRODUCT_ADDONS_PLUGIN_URL . '/assets/css/frontend/frontend.css', ['dashicons'], WC_PRODUCT_ADDONS_VERSION);
    }
    wp_localize_script('dlp-fe-home-quickview', 'dlpQV', ['ajaxurl' => admin_url('admin-ajax.php')]);
}, 30);

/*
 * El carrito lateral añade por ajax, pero WooCommerce guarda igual el aviso "X han sido añadidos a tu carrito"
 * y aparecía en la siguiente página. Con el carrito lateral abierto ese aviso sobra.
 */
// El carrito lateral usa el endpoint de WooCommerce (?wc-ajax=xoo_wsc_add_to_cart).
add_action('wc_ajax_xoo_wsc_add_to_cart', function () {
    add_action('shutdown', 'wc_clear_notices', 5);
}, 0);
