<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Mi cuenta personalizada: Pedidos, Direcciones, Perfil, Cerrar sesión.
 * - El menú de WooCommerce se reduce a esas secciones y el inicio (/mi-cuenta/) redirige a Pedidos.
 * - La cabecera con saludo y pestañas la dibuja este módulo; el menú original se oculta por CSS.
 * - Direcciones usa la libreta propia (checkout-libreta): lista, eliminar y agregar con mapa (dlp-tiendas).
 */
function dlp_fe_acc_icon($name) {
    $p = [
        'orders'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/>',
        'address' => '<path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.4"/>',
        'profile' => '<circle cx="12" cy="8.2" r="3.7"/><path d="M4.8 20c.6-3.7 3.6-5.8 7.2-5.8s6.6 2.1 7.2 5.8"/>',
        'logout'  => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="M10 8l-4 4 4 4M6 12h10"/>',
    ];
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $p[$name] . '</svg>';
}

add_filter('woocommerce_account_menu_items', function ($items) {
    return array_intersect_key($items, array_flip(['orders', 'edit-address', 'edit-account', 'customer-logout']));
}, 99);

add_action('template_redirect', function () {
    if (function_exists('is_account_page') && is_account_page() && is_user_logged_in() && !is_wc_endpoint_url()) {
        wp_safe_redirect(wc_get_account_endpoint_url('orders'));
        exit;
    }
});

// Cabecera: saludo + pestañas con ícono
add_action('woocommerce_before_account_navigation', function () {
    $u    = wp_get_current_user();
    $name = $u->first_name ?: $u->display_name;
    $tabs = [
        'orders'       => ['Pedidos', 'orders', is_wc_endpoint_url('orders') || is_wc_endpoint_url('view-order')],
        'edit-address' => ['Direcciones', 'address', is_wc_endpoint_url('edit-address')],
        'edit-account' => ['Perfil', 'profile', is_wc_endpoint_url('edit-account')],
    ];
    echo '<div class="dlpac-head"><div class="dlpac-hello">' . esc_html(DLP_FE_Registry::setting('cuenta-pagina', 'saludo')) . ', ' . esc_html($name) . '</div><div class="dlpac-mail">' . esc_html($u->user_email) . '</div><nav class="dlpac-tabs" aria-label="Mi cuenta">';
    foreach ($tabs as $ep => $t) {
        echo '<a class="dlpac-tab' . ($t[2] ? ' is-on' : '') . '" href="' . esc_url(wc_get_account_endpoint_url($ep)) . '">' . dlp_fe_acc_icon($t[1]) . '<span>' . esc_html($t[0]) . '</span></a>';
    }
    echo '</nav></div>';
});

// Al final del contenido de cada sección (después de pedidos / direcciones / perfil)
add_action('woocommerce_account_content', function () {
    echo '<a class="dlpac-logout" href="' . esc_url(wc_logout_url()) . '">' . dlp_fe_acc_icon('logout') . '<span>Cerrar sesión</span></a>';
}, 99);

// Direcciones: libreta propia en lugar de las direcciones de facturación/envío de WooCommerce
add_action('wp', function () {
    if (function_exists('is_account_page') && is_account_page() && is_wc_endpoint_url('edit-address')) {
        remove_action('woocommerce_account_edit-address_endpoint', 'woocommerce_account_edit_address');
        add_action('woocommerce_account_edit-address_endpoint', 'dlp_fe_acc_render_addresses');
    }
});

function dlp_fe_acc_render_addresses() {
    if (!function_exists('dlp_fe_lib_get')) {
        echo '<p>La libreta de direcciones no está activa.</p>';
        return;
    }
    $list = dlp_fe_lib_get(get_current_user_id());
    $max  = max(1, (int) DLP_FE_Registry::setting('checkout-libreta', 'max'));
    echo '<div class="dlpac-addr" data-max="' . (int) $max . '"><p class="dlpac-count">' . count($list) . ' de ' . $max . ' direcciones guardadas</p>';
    foreach ($list as $a) {
        $st = dlp_fe_lib_status($a);
        echo '<div class="dlpac-card" data-id="' . esc_attr($a['id']) . '"><div class="dlpac-main"><b>' . esc_html($a['name']) . '</b> <span class="dlpac-chip ' . ($st['ok'] ? 'ok' : 'no') . '">' . ($st['ok'] ? 'Abierta' : 'Cerrada') . '</span>'
            . '<div class="dlpac-a">' . esc_html($a['address'] ?: ($a['ref'] ?: 'Punto en el mapa')) . '</div>'
            . ($a['ref'] && $a['address'] ? '<div class="dlpac-s">' . esc_html($a['ref']) . '</div>' : '')
            . ($st['store'] ? '<div class="dlpac-s">' . esc_html($st['store']) . '</div>' : '')
            . ($st['ok'] ? '' : '<div class="dlpac-msg">' . esc_html($st['msg']) . '</div>')
            . '</div><button type="button" class="dlpac-del">Eliminar</button></div>';
    }
    if (!$list) {
        echo '<p class="dlpac-empty">Aún no tienes direcciones guardadas.</p>';
    }
    if (count($list) < $max) {
        echo '<button type="button" class="dlpac-add">+ Agregar dirección</button>';
    } else {
        echo '<p class="dlpac-empty">Llegaste al máximo. Elimina una dirección para agregar otra.</p>';
    }
    echo '</div>';
    ?>
    <div class="dlpac-modal" hidden>
        <div class="dlpac-sheet" role="dialog" aria-modal="true" aria-label="Agregar dirección">
            <button type="button" class="dlpac-x" aria-label="Cerrar">&times;</button>
            <h3>Agregar dirección</h3>
            <p class="dlpac-hint">Toca el mapa para marcar el punto exacto de entrega.</p>
            <div class="dlpac-map" id="dlpac-map"></div>
            <div class="dlpac-cov" role="status" aria-live="polite"></div>
            <label>Nombre (Casa, Trabajo…)<input type="text" id="dlpac-name" maxlength="40"></label>
            <label>Dirección completa <em>(opcional)</em><input type="text" id="dlpac-address" maxlength="160"></label>
            <label>Referencia <em>(opcional)</em><input type="text" id="dlpac-ref" maxlength="120"></label>
            <button type="button" class="dlpac-save" disabled>Guardar dirección</button>
        </div>
    </div>
    <?php
}

add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_account_page') || !is_account_page() || !is_user_logged_in()) {
        return;
    }
    $data = ['ajaxurl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('dlp_fe_lib')];
    if (is_wc_endpoint_url('edit-address') && function_exists('dlp_tiendas_map_config') && defined('DLP_TIENDAS_URL')) {
        $u = DLP_TIENDAS_URL . 'assets/';
        wp_enqueue_style('dlp-leaflet', $u . 'vendor/leaflet.css', [], '1.9.4');
        wp_enqueue_script('dlp-leaflet', $u . 'vendor/leaflet.js', [], '1.9.4', true);
        wp_enqueue_script('dlp-protomaps', $u . 'vendor/protomaps-leaflet.js', ['dlp-leaflet'], '5.1.0', true);
        $data['map']      = dlp_tiendas_map_config();
        $data['covNonce'] = wp_create_nonce('dlp_tiendas_checkout');
    }
    if (wp_script_is('dlp-fe-cuenta-pagina', 'enqueued') || wp_script_is('dlp-fe-cuenta-pagina', 'registered')) {
        wp_localize_script('dlp-fe-cuenta-pagina', 'dlpAcc', $data);
    }
}, 20);

// Guardar una dirección desde Mi cuenta (cobertura obligatoria; el horario solo informa)
add_action('wp_ajax_dlp_fe_lib_save', function () {
    check_ajax_referer('dlp_fe_lib', 'nonce');
    $uid = get_current_user_id();
    $lat = isset($_POST['lat']) ? wc_clean(wp_unslash($_POST['lat'])) : '';
    $lng = isset($_POST['lng']) ? wc_clean(wp_unslash($_POST['lng'])) : '';
    $name = isset($_POST['name']) ? mb_substr(sanitize_text_field(wp_unslash($_POST['name'])), 0, 40) : '';
    if (!$uid || $name === '' || !is_numeric($lat) || !is_numeric($lng) || abs($lat) > 90 || abs($lng) > 180 || !function_exists('dlp_fe_lib_get')) {
        wp_send_json_error(['msg' => 'Faltan datos de la dirección.']);
    }
    if (function_exists('dlp_tiendas_check_point')) {
        $r = dlp_tiendas_check_point($lat, $lng);
        if (in_array($r['reason'] ?? '', ['sin_cobertura', 'punto_invalido'], true)) {
            wp_send_json_error(['msg' => $r['msg']]);
        }
    }
    $list = dlp_fe_lib_get($uid);
    $max  = max(1, (int) DLP_FE_Registry::setting('checkout-libreta', 'max'));
    if (count($list) >= $max) {
        wp_send_json_error(['msg' => 'Ya tienes ' . $max . ' direcciones. Elimina una para agregar otra.']);
    }
    $list[] = [
        'id'      => substr(md5(uniqid('', true)), 0, 10),
        'name'    => $name,
        'address' => isset($_POST['address']) ? sanitize_text_field(wp_unslash($_POST['address'])) : '',
        'ref'     => isset($_POST['ref']) ? sanitize_text_field(wp_unslash($_POST['ref'])) : '',
        'lat'     => round((float) $lat, 7),
        'lng'     => round((float) $lng, 7),
    ];
    update_user_meta($uid, DLP_FE_LIB_META, array_values($list));
    wp_send_json_success();
});
