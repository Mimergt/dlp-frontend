<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pedido mínimo: barra de progreso + postres sugeridos en el carrito lateral (Xootix) y en el checkout.
 * El mínimo y el tipo de pedido (delivery/pickup) vienen de Del Puente Tiendas; sin ese plugin no muestra nada.
 * El bloqueo real del pedido sigue siendo el de Tiendas (checkout); esto solo ayuda al cliente a llegar.
 */
function dlp_fe_pm_estado() {
    if (!function_exists('dlp_tiendas_minimo') || !function_exists('dlp_tiendas_current_type') || !function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
        return null;
    }
    $tipo = dlp_tiendas_current_type();
    $min  = (float) dlp_tiendas_minimo($tipo);
    if ($min <= 0) {
        return null;
    }
    $sub = (float) WC()->cart->get_subtotal() + (float) WC()->cart->get_subtotal_tax();
    return ['min' => $min, 'sub' => $sub, 'falta' => max(0, $min - $sub), 'tipo' => $tipo];
}

function dlp_fe_pm_sugeridos($falta) {
    $opts = get_option('dlp_fe_modules', []);
    $s = $opts['pedido-minimo']['settings'] ?? [];
    $cat = sanitize_title($s['categoria'] ?? 'postres') ?: 'postres';
    $max = max(1, (int) ($s['cantidad'] ?? 6));
    $lista = [];
    foreach (wc_get_products(['category' => [$cat], 'status' => 'publish', 'limit' => 20, 'type' => 'simple', 'stock_status' => 'instock', 'orderby' => 'menu_order title', 'order' => 'ASC']) as $p) {
        if ($p->get_price() === '' || !$p->is_purchasable()) {
            continue;
        }
        if (function_exists('WC_Product_Addons_Helper') || class_exists('WC_Product_Addons_Helper')) {
            if (WC_Product_Addons_Helper::get_product_addons($p->get_id())) {
                continue;
            }
        }
        $lista[] = $p;
    }
    // Primero los que cubren por sí solos lo que falta; luego del más barato al más caro.
    usort($lista, function ($a, $b) use ($falta) {
        $ca = (float) $a->get_price() >= $falta ? 0 : 1;
        $cb = (float) $b->get_price() >= $falta ? 0 : 1;
        return $ca <=> $cb ?: ((float) $a->get_price() <=> (float) $b->get_price());
    });
    return array_slice($lista, 0, $max);
}

function dlp_fe_pm_html($contexto = 'carrito') {
    $e = dlp_fe_pm_estado();
    if (!$e) {
        return '';
    }
    $ok  = $e['falta'] <= 0.0001;
    if ($ok && $contexto === 'checkout') {
        return ''; // en el checkout solo se muestra mientras falte monto
    }
    $pct = min(100, round($e['sub'] / $e['min'] * 100));
    $q   = function ($n) {
        return 'Q' . number_format((float) $n, $n == floor($n) ? 0 : 2);
    };
    $h  = '<div class="dlpmin dlpmin--' . esc_attr($contexto) . ($ok ? ' dlpmin--ok' : '') . '" data-falta="' . esc_attr($e['falta']) . '">';
    $h .= '<div class="dlpmin-top"><b>' . ($ok ? 'Ya alcanzas el pedido mínimo' : 'Te faltan ' . esc_html($q($e['falta'])) . ' para el pedido mínimo') . '</b>';
    $h .= '<span>' . esc_html($q($e['sub'])) . ' / ' . esc_html($q($e['min'])) . '</span></div>';
    $h .= '<div class="dlpmin-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int) $pct . '"><i style="width:' . (int) $pct . '%"></i></div>';
    if (!$ok) {
        if (!$e['tipo'] || $e['tipo'] === 'delivery') {
            $h .= '<p class="dlpmin-nota">Mínimo para pedidos a domicilio.</p>';
        }
        $sug = dlp_fe_pm_sugeridos($e['falta']);
        if ($sug) {
            $h .= '<div class="dlpmin-tit">Completa con un postre</div><div class="dlpmin-fila">';
            foreach ($sug as $p) {
                $img = get_the_post_thumbnail_url($p->get_id(), 'woocommerce_thumbnail') ?: wc_placeholder_img_src();
                $h .= '<div class="dlpmin-it"><span class="dlpmin-img"><img src="' . esc_url($img) . '" alt="" loading="lazy"></span>';
                $h .= '<a href="' . esc_url('?add-to-cart=' . $p->get_id()) . '" data-product_id="' . (int) $p->get_id() . '" data-quantity="1" rel="nofollow" aria-label="' . esc_attr('Agregar ' . $p->get_name()) . '" class="dlpmin-add add_to_cart_button ajax_add_to_cart">+</a>';
                $h .= '<b>' . esc_html($p->get_name()) . '</b><em>' . wp_kses_post(wc_price($p->get_price())) . '</em></div>';
            }
            $h .= '</div>';
        }
        $h .= '<a class="dlpmin-seguir" href="' . esc_url(home_url('/menu/')) . '">Seguir pidiendo</a>';
    }
    return $h . '</div>';
}

// Carrito lateral: después de los productos (se vuelve a pintar con cada cambio del carrito).
add_action('xoo_wsc_after_products', function () {
    echo dlp_fe_pm_html('carrito'); // phpcs:ignore WordPress.Security.EscapeOutput
});

// Checkout: arriba del resumen del pedido; el aviso de texto de Tiendas se oculta por CSS (el bloqueo real sigue ahí).
add_action('woocommerce_review_order_before_submit', function () {
    echo dlp_fe_pm_html('checkout'); // phpcs:ignore WordPress.Security.EscapeOutput
}, 5);
