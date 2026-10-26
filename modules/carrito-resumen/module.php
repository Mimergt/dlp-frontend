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

/** nombre del extra de cantidad → título del grupo (sin «Elige tus N »). */
function dlp_fe_cr_grupos($product_id) {
    $mapa = [];
    if (!class_exists('WC_Product_Addons_Helper')) {
        return $mapa;
    }
    foreach ((array) WC_Product_Addons_Helper::get_product_addons($product_id) as $a) {
        if (($a['type'] ?? '') === 'input_multiplier' && function_exists('dlp_fe_xg_marca') && ($g = dlp_fe_xg_marca($a['description'] ?? ''))) {
            $t = preg_replace('/^Elige tus? \d+\s+/iu', '', $g['titulo']);
            $mapa[dlp_fe_cr_txt($a['name'])] = mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
        }
    }
    return $mapa;
}

add_filter('woocommerce_get_item_data', function ($data, $cart_item) {
    if (empty($cart_item['addons']) || !is_array($data) || count($data) < 6) {
        return $data; // pocas líneas: se deja como está
    }
    $grupos = dlp_fe_cr_grupos((int) $cart_item['product_id']);
    $compl  = [];   // etiqueta => cantidad
    $cant   = [];   // título del grupo => [etiqueta => cantidad]
    $quitar = [];   // hamburguesa => ingredientes
    $out    = [];
    $pos    = ['compl' => null, 'quitar' => null];
    $posG   = [];
    foreach ($data as $row) {
        $k = dlp_fe_cr_txt($row['key'] ?? ($row['name'] ?? '')); // los Add-Ons usan «name», WooCommerce acepta «key» o «name»
        $v = dlp_fe_cr_txt(!empty($row['display']) ? $row['display'] : ($row['value'] ?? '')); // «display» puede venir vacío
        if (preg_match('/^Complemento \d+$/iu', $k)) {
            $base = trim(preg_replace('/\s*\(\+.*\)\s*$/u', '', $v));
            $compl[$base] = ($compl[$base] ?? 0) + 1;
            if ($pos['compl'] === null) { $pos['compl'] = count($out); $out[] = null; }
            continue;
        }
        if (isset($grupos[$k]) && ctype_digit($v)) {
            if ((int) $v > 0) {
                $cant[$grupos[$k]][$k] = (int) $v;
            }
            if (!isset($posG[$grupos[$k]])) { $posG[$grupos[$k]] = count($out); $out[] = null; }
            continue;
        }
        if (preg_match('/^(.+?):\s*quitar ingredientes$/iu', $k, $m)) {
            $quitar[trim($m[1])][] = trim(preg_replace('/^Sin\s+/iu', '', $v));
            if ($pos['quitar'] === null) { $pos['quitar'] = count($out); $out[] = null; }
            continue;
        }
        $out[] = $row;
    }
    if ($pos['compl'] !== null) {
        $partes = [];
        foreach ($compl as $b => $n) { $partes[] = $n . ' ' . $b; }
        $out[$pos['compl']] = ['key' => 'Complementos', 'name' => 'Complementos', 'value' => implode(', ', $partes), 'display' => esc_html(implode(', ', $partes))];
    }
    foreach ($posG as $titulo => $i) {
        $partes = [];
        foreach ($cant[$titulo] ?? [] as $b => $n) { $partes[] = $n . ' ' . $b; }
        $out[$i] = ['key' => $titulo, 'name' => $titulo, 'value' => implode(', ', $partes), 'display' => esc_html(implode(', ', $partes))];
    }
    if ($pos['quitar'] !== null) {
        $lineas = [];
        foreach ($quitar as $h => $ing) { $lineas[] = '<b>' . esc_html($h) . ':</b> ' . esc_html(implode(', ', $ing)); }
        $total = array_sum(array_map('count', $quitar));
        $out[$pos['quitar']] = ['key' => 'Sin ingredientes', 'name' => 'Sin ingredientes', 'value' => $total . ' quitados',
            'display' => '<span class="dlp-cr-det">' . implode('<br>', $lineas) . '</span>'];
    }
    return array_values(array_filter($out));
}, 999, 2);
