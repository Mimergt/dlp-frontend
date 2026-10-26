<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resumen corto de las opciones de un producto en el carrito (solo presentación; el pedido guarda cada opción como siempre).
 *  - «Complemento 1…N» (listas)            → «Complementos: 2 Papas fritas, 1 Aros de cebolla»
 *  - Cantidades de un grupo [grupo:Elige tus N bebidas=N] → «Bebidas: 3 Frezka Rosa de Jamaica, 1 Tiky»
 *  - «<Hamburguesa>: quitar ingredientes» repetido por ingrediente → «Sin ingredientes» con una línea por hamburguesa
 */
function dlp_fe_cr_txt($v) {
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(wp_strip_all_tags((string) $v), ENT_QUOTES, 'UTF-8')));
}

/** «Q10.00» (con el símbolo de la tienda) a partir de un número. */
function dlp_fe_cr_q($n) {
    return get_woocommerce_currency_symbol() . number_format((float) $n, 2, '.', ',');
}

/** Precio extra que trae el texto de una opción: «Aros de cebolla (+ Q10.00)» → [«Aros de cebolla», 10.0]. */
function dlp_fe_cr_precio($v) {
    $base = trim(preg_replace('/\s*\(\+.*\)\s*$/u', '', $v));
    $n = 0.0;
    if (preg_match('/\(\+\s*([^)]*)\)\s*$/u', $v, $m)) {
        $n = (float) str_replace(',', '', preg_replace('/[^0-9.,]/', '', $m[1]));
    }
    return [$base, $n];
}

/** nombre del extra de cantidad → título del grupo (sin «Elige tus N »). */
function dlp_fe_cr_grupos($product_id) {
    $mapa = [];
    if (!class_exists('WC_Product_Addons_Helper')) {
        return $mapa;
    }
    foreach ((array) WC_Product_Addons_Helper::get_product_addons($product_id) as $a) {
        if (($a['type'] ?? '') === 'input_multiplier' && function_exists('dlp_fe_xg_marca') && ($g = dlp_fe_xg_marca($a['description'] ?? ''))) {
            $t = preg_replace(['/^Elige tus? \d+\s+/iu', '/^Añade\s+/iu'], '', $g['titulo']);
            $mapa[dlp_fe_cr_txt($a['name'])] = ['t' => mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1), 'p' => !empty($a['adjust_price']) ? (float) $a['price'] : 0.0];
        }
    }
    return $mapa;
}

add_filter('woocommerce_get_item_data', function ($data, $cart_item) {
    if (empty($cart_item['addons']) || !is_array($data)) {
        return $data;
    }
    // Filas con el mismo título (p. ej. «Ingredientes» una vez por ingrediente quitado) → una sola con los valores juntos
    $uni = [];
    foreach ($data as $row) {
        $k = dlp_fe_cr_txt($row['key'] ?? ($row['name'] ?? ''));
        $v = dlp_fe_cr_txt(!empty($row['display']) ? $row['display'] : ($row['value'] ?? ''));
        if ($k !== '' && !preg_match('/^(Complemento \d+|.+:\s*(complemento|carne|bebida|quitar ingredientes))$/iu', $k) && isset($uni[$k])) {
            $uni[$k]['_v'][] = $v;
            continue;
        }
        $row['_v'] = [$v];
        $uni[$k !== '' ? $k : count($uni) . '#'] = $row;
    }
    $data = [];
    foreach ($uni as $row) {
        $vs = $row['_v']; unset($row['_v']);
        if (count($vs) > 1) {
            $t = implode(', ', $vs);
            $row['value'] = $t; $row['display'] = esc_html($t);
        }
        $data[] = $row;
    }
    // Pocas líneas: se deja como está, salvo en promos por producto («Carnuda 1: complemento»), que siempre van resumidas
    $porProducto = false;
    foreach ($data as $row) {
        if (preg_match('/:\s*(complemento|carne|bebida)$/iu', dlp_fe_cr_txt($row['key'] ?? ($row['name'] ?? '')))) { $porProducto = true; break; }
    }
    if (count($data) < 6 && !$porProducto) {
        return $data;
    }
    $grupos = dlp_fe_cr_grupos((int) $cart_item['product_id']);
    $compl  = [];   // etiqueta => cantidad
    $cant   = [];   // título del grupo => [etiqueta => cantidad]
    $quitar = [];   // hamburguesa => ingredientes
    $out    = [];
    $pos    = ['compl' => null, 'quitar' => null];
    $posG   = [];
    $porProd = []; // producto (Carnuda 1, Del Puente 2…) => ['c' => complemento, 's' => ingredientes quitados]
    $posProd = [];
    foreach ($data as $row) {
        $k = dlp_fe_cr_txt($row['key'] ?? ($row['name'] ?? '')); // los Add-Ons usan «name», WooCommerce acepta «key» o «name»
        $v = dlp_fe_cr_txt(!empty($row['display']) ? $row['display'] : ($row['value'] ?? '')); // «display» puede venir vacío
        if (preg_match('/^Complemento \d+$/iu', $k)) {
            [$base, $pr] = dlp_fe_cr_precio($v);
            $compl[$base][0] = ($compl[$base][0] ?? 0) + 1;
            $compl[$base][1] = ($compl[$base][1] ?? 0) + $pr;
            if ($pos['compl'] === null) { $pos['compl'] = count($out); $out[] = null; }
            continue;
        }
        if (isset($grupos[$k]) && ctype_digit($v)) {
            $gt = $grupos[$k]['t'];
            if ((int) $v > 0) {
                $cant[$gt][preg_replace('/^Extra · /u', '', $k)] = [(int) $v, $grupos[$k]['p'] * (int) $v];
            }
            if (!isset($posG[$gt])) { $posG[$gt] = count($out); $out[] = null; }
            continue;
        }
        if (preg_match('/^(.+?):\s*(complemento|carne|bebida)$/iu', $k, $m)) {
            $h = trim($m[1]);
            [$cb, $cp] = dlp_fe_cr_precio($v);
            $porProd[$h][strtolower($m[2]) === 'complemento' ? 'c' : (strtolower($m[2]) === 'carne' ? 'm' : 'b')][] = $cb . ($cp > 0 ? ' (+' . dlp_fe_cr_q($cp) . ')' : '');
            if (!isset($posProd[$h])) { $posProd[$h] = count($out); $out[] = null; }
            continue;
        }
        if (preg_match('/^(.+?):\s*quitar ingredientes$/iu', $k, $m)) {
            $h = trim($m[1]);
            $ing = trim(preg_replace('/^Sin\s+/iu', '', $v));
            $quitar[$h][] = $ing;
            $porProd[$h]['s'][] = $ing;
            if (!isset($posProd[$h])) { $posProd[$h] = count($out); $out[] = null; }
            if ($pos['quitar'] === null) { $pos['quitar'] = count($out); $out[] = null; }
            continue;
        }
        $out[] = $row;
    }
    if ($pos['compl'] !== null) {
        $partes = [];
        foreach ($compl as $b => [$n, $pt]) { $partes[] = $n . ' ' . $b . ($pt > 0 ? ' (+' . dlp_fe_cr_q($pt) . ')' : ''); }
        $out[$pos['compl']] = ['key' => 'Complementos', 'name' => 'Complementos', 'value' => implode(', ', $partes), 'display' => esc_html(implode(', ', $partes))];
    }
    foreach ($posG as $titulo => $i) {
        $partes = [];
        foreach ($cant[$titulo] ?? [] as $b => [$n, $pt]) { $partes[] = $n . ' ' . $b . ($pt > 0 ? ' (+' . dlp_fe_cr_q($pt) . ')' : ''); }
        $out[$i] = ['key' => $titulo, 'name' => $titulo, 'value' => implode(', ', $partes), 'display' => esc_html(implode(', ', $partes))];
    }
    // Si hay «<Producto>: complemento», se muestra una línea por producto con su complemento y lo que se le quitó
    $hayComp = false;
    foreach ($porProd as $pp) { if (!empty($pp['c']) || !empty($pp['b']) || !empty($pp['m'])) { $hayComp = true; } }
    if ($hayComp) {
        foreach ($posProd as $h => $i) {
            $c = $porProd[$h]['c'] ?? [];
            $sn = $porProd[$h]['s'] ?? [];
            $partes = array_filter([implode(', ', $porProd[$h]['m'] ?? []), $c ? implode(', ', $c) : '—', implode(', ', $porProd[$h]['b'] ?? [])]);
            $t = implode(' · ', $partes) . ($sn ? ' · Sin ' . implode(', ', $sn) : '');
            $out[$i] = ['key' => $h, 'name' => $h, 'value' => $t, 'display' => esc_html($t)];
        }
        $pos['quitar'] = null;
    } else {
        foreach ($posProd as $i) { $out[$i] = null; }
    }
    if ($pos['quitar'] !== null && !$hayComp) {
        $lineas = [];
        foreach ($quitar as $h => $ing) { $lineas[] = '<b>' . esc_html($h) . ':</b> ' . esc_html(implode(', ', $ing)); }
        $total = array_sum(array_map('count', $quitar));
        $out[$pos['quitar']] = ['key' => 'Sin ingredientes', 'name' => 'Sin ingredientes', 'value' => $total . ' quitados',
            'display' => '<span class="dlp-cr-det">' . implode('<br>', $lineas) . '</span>'];
    }
    return array_values(array_filter($out));
}, 999, 2);

/*
 * WooCommerce y el carrito lateral guardan en el navegador (sessionStorage) el HTML del carrito y solo lo piden de nuevo cuando
 * cambia el «hash» del carrito. Al publicar una versión nueva, quien ya tenía productos veía el formato viejo hasta tocar su carrito;
 * incluir la versión del plugin en el hash fuerza a refrescar una vez.
 */
add_filter('woocommerce_cart_hash', function ($hash) {
    return $hash ? md5($hash . DLP_FE_VERSION) : $hash;
});
