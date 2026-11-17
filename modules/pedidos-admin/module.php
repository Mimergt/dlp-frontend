<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Pedidos (admin): columnas y filtros Tipo / Origen / Pago en la lista HPOS (WooCommerce → Pedidos).
 * Tipo   = meta woofood_order_type ('pickup'; cualquier otro valor o vacío = delivery, como el panel).
 * Origen = landing page de HandL (origin=webview&device=ios|android) o, si el pedido no tiene landing, la meta device (IOS/Android/MWeb).
 *          Misma regla que el Excel del panel (dlp-panel3 export.php) → dlp_fe_origen_pedido().
 * Pago   = payment_method: 'cod' = Efectivo; 'cybersource*' = Tarjeta; otro: su título.
 * Los filtros se aplican con subconsultas EXISTS sobre wc_orders_meta (sin JOIN: no duplican filas ni piden volver a leer pedidos).
 */

/** Origen de un pedido: ['clave' => 'android|ios|web', 'etiqueta' => 'Android|iOS|Web']. Centralizado: admin, panel y Excel. */
function dlp_fe_origen_pedido($order) {
    if (!is_object($order) || !method_exists($order, 'get_meta')) {
        return ['clave' => 'web', 'etiqueta' => 'Web'];
    }
    $landing = html_entity_decode((string) $order->get_meta('handl_landing_page', true));
    $device  = strtolower((string) $order->get_meta('device', true));
    $clave   = 'web';
    if (stripos($landing, 'origin=webview') !== false) {
        if (stripos($landing, 'device=ios') !== false) {
            $clave = 'ios';
        } elseif (stripos($landing, 'device=android') !== false) {
            $clave = 'android';
        }
    } elseif ($landing === '') {
        if ($device === 'ios') {
            $clave = 'ios';
        } elseif ($device === 'android') {
            $clave = 'android';
        }
    }
    $et = ['android' => 'Android', 'ios' => 'iOS', 'web' => 'Web'];
    return ['clave' => $clave, 'etiqueta' => $et[$clave]];
}

function dlp_fe_pa_tipo($order) {
    return $order->get_meta('woofood_order_type', true) === 'pickup' ? 'pickup' : 'delivery';
}

function dlp_fe_pa_pago($order) {
    $m = (string) $order->get_payment_method();
    if ($m === 'cod') {
        return ['clave' => 'efectivo', 'etiqueta' => 'Efectivo'];
    }
    if (strpos($m, 'cybersource') === 0) {
        return ['clave' => 'tarjeta', 'etiqueta' => 'Tarjeta'];
    }
    $t = (string) $order->get_payment_method_title();
    return ['clave' => 'otro', 'etiqueta' => $t !== '' ? $t : ($m !== '' ? $m : '—')];
}

/* ---- Filtros (valores en la URL) ---- */
function dlp_fe_pa_filtros() {
    $f = [
        'dlp_tipo'   => ['delivery' => 'Delivery', 'pickup' => 'Pickup'],
        'dlp_origen' => ['web' => 'Web', 'android' => 'Android', 'ios' => 'iOS'],
        'dlp_pago'   => ['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta'],
    ];
    return $f;
}

function dlp_fe_pa_valor($k) {
    $v = isset($_GET[$k]) ? sanitize_key(wp_unslash($_GET[$k])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $f = dlp_fe_pa_filtros();
    return isset($f[$k][$v]) ? $v : '';
}

/** ¿Es la lista de pedidos HPOS del administrador? */
function dlp_fe_pa_es_lista() {
    return is_admin() && isset($_GET['page']) && $_GET['page'] === 'wc-orders' && empty($_GET['action']); // phpcs:ignore WordPress.Security.NonceVerification
}

add_action('woocommerce_order_list_table_restrict_manage_orders', function ($order_type = 'shop_order') {
    if ($order_type !== 'shop_order') {
        return;
    }
    $rot = ['dlp_tipo' => 'Tipo', 'dlp_origen' => 'Origen app', 'dlp_pago' => 'Pago'];
    foreach (dlp_fe_pa_filtros() as $k => $opts) {
        $sel = dlp_fe_pa_valor($k);
        echo '<select name="' . esc_attr($k) . '" id="' . esc_attr($k) . '"><option value="">' . esc_html($rot[$k]) . ': todos</option>';
        foreach ($opts as $v => $l) {
            echo '<option value="' . esc_attr($v) . '"' . selected($sel, $v, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select>';
    }
});

/** SQL de cada filtro sobre la tabla HPOS (alias = nombre de la tabla de pedidos). */
function dlp_fe_pa_sql($k, $v, $o, $m) {
    $ex = function ($key, $cond) use ($o, $m) {
        return "EXISTS (SELECT 1 FROM {$m} dm WHERE dm.order_id = {$o}.id AND dm.meta_key = '" . esc_sql($key) . "' AND {$cond})";
    };
    if ($k === 'dlp_tipo') {
        $pk = $ex('woofood_order_type', "dm.meta_value = 'pickup'");
        return $v === 'pickup' ? $pk : "NOT {$pk}";
    }
    if ($k === 'dlp_pago') {
        return $v === 'efectivo' ? "{$o}.payment_method = 'cod'" : "{$o}.payment_method LIKE 'cybersource%'";
    }
    if ($k === 'dlp_origen') {
        $con_landing = $ex('handl_landing_page', "dm.meta_value <> ''");
        $app = function ($dev) use ($ex) {
            return $ex('handl_landing_page', "dm.meta_value LIKE '%origin=webview%' AND dm.meta_value LIKE '%device=" . $dev . "%'");
        };
        $ios     = '(' . $app('ios') . " OR (NOT {$con_landing} AND " . $ex('device', "dm.meta_value = 'ios'") . '))';
        $android = '(' . $app('android') . " OR (NOT {$con_landing} AND " . $ex('device', "dm.meta_value = 'android'") . '))';
        if ($v === 'ios') {
            return $ios;
        }
        if ($v === 'android') {
            return $android;
        }
        return "(NOT {$ios} AND NOT {$android})";
    }
    return '';
}

/*
 * Los filtros se aplican por clauses (no como argumentos de la consulta). WooCommerce solo cuenta resultados y calcula las páginas
 * cuando la consulta lleva algún argumento extra (si no, usa el total de pedidos por estado y la paginación sale mal).
 * Un argumento propio, que WooCommerce ignora, basta para que cuente los resultados filtrados.
 */
add_filter('woocommerce_order_list_table_prepare_items_query_args', function ($args) {
    foreach (array_keys(dlp_fe_pa_filtros()) as $k) {
        if (dlp_fe_pa_valor($k) !== '') {
            $args['dlp_filtros'] = 1;
            break;
        }
    }
    return $args;
}, 20);

add_filter('woocommerce_orders_table_query_clauses', function ($clauses, $query = null, $args = []) {
    if (!dlp_fe_pa_es_lista()) {
        return $clauses;
    }
    global $wpdb;
    $o = $wpdb->prefix . 'wc_orders';
    $m = $wpdb->prefix . 'wc_orders_meta';
    foreach (array_keys(dlp_fe_pa_filtros()) as $k) {
        $v = dlp_fe_pa_valor($k);
        if ($v === '') {
            continue;
        }
        $sql = dlp_fe_pa_sql($k, $v, $o, $m);
        if ($sql !== '') {
            $clauses['where'] = (isset($clauses['where']) ? $clauses['where'] : '') . ' AND (' . $sql . ')';
        }
    }
    return $clauses;
}, 20, 3);

/* ---- Columnas ---- */
add_filter('woocommerce_shop_order_list_table_columns', function ($cols) {
    // «Origen app»: WooCommerce ya trae su propia columna «Origen» (atribución de marketing) al final de la lista.
    $nuevo = ['dlp_tipo' => 'Tipo', 'dlp_origen' => 'Origen app', 'dlp_pago' => 'Pago'];
    $out = [];
    $puesto = false;
    foreach ($cols as $k => $v) {
        $out[$k] = $v;
        if ($k === 'dlp_tienda' || (!isset($cols['dlp_tienda']) && $k === 'order_status')) {
            $out = array_merge($out, $nuevo);
            $puesto = true;
        }
    }
    return $puesto ? $out : array_merge($out, $nuevo);
}, 30);

add_action('woocommerce_shop_order_list_table_custom_column', function ($col, $order) {
    if (!in_array($col, ['dlp_tipo', 'dlp_origen', 'dlp_pago'], true) || !is_object($order) || $order->get_type() !== 'shop_order') {
        return;
    }
    if ($col === 'dlp_tipo') {
        $t = dlp_fe_pa_tipo($order);
        echo '<span class="dlppa dlppa-' . esc_attr($t) . '">' . ($t === 'pickup' ? 'Pickup' : 'Delivery') . '</span>';
    } elseif ($col === 'dlp_origen') {
        $r = dlp_fe_origen_pedido($order);
        echo '<span class="dlppa dlppa-' . esc_attr($r['clave']) . '">' . esc_html($r['etiqueta']) . '</span>';
    } else {
        $p = dlp_fe_pa_pago($order);
        echo '<span class="dlppa dlppa-' . esc_attr($p['clave']) . '">' . esc_html($p['etiqueta']) . '</span>';
    }
}, 10, 2);

add_action('admin_head', function () {
    if (!dlp_fe_pa_es_lista()) {
        return;
    }
    echo '<style>.column-dlp_tipo,.column-dlp_origen,.column-dlp_pago{width:7%}'
        . '.dlppa{display:inline-block;padding:2px 9px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.6;white-space:nowrap;background:#eef0f3;color:#3c434a}'
        . '.dlppa-delivery{background:#e6efff;color:#1d4ed8}.dlppa-pickup{background:#fff1d6;color:#92400e}'
        . '.dlppa-android{background:#e2f6e9;color:#15803d}.dlppa-ios{background:#e5e7eb;color:#111}'
        . '.dlppa-efectivo{background:#e2f6e9;color:#166534}.dlppa-tarjeta{background:#efe7ff;color:#5b21b6}</style>';
});
