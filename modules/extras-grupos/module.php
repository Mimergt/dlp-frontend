<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Grupos de extras de cantidad con total exacto. En cada extra «Cantidad» del plugin de extras, la descripción lleva
 * [grupo:Título=N]; todos los extras con el mismo título forman un grupo cuyo total debe ser N. La vista rápida
 * (home-quickview) pinta el contador y bloquea el botón; aquí se valida en el servidor para que no se pueda saltar.
 */
function dlp_fe_xg_marca($descripcion) {
    if (is_string($descripcion) && preg_match('/\[grupo:([^=\]]+)=(\d+)\]/i', $descripcion, $m)) {
        return ['titulo' => trim($m[1]), 'n' => (int) $m[2]];
    }
    return null;
}

add_filter('woocommerce_add_to_cart_validation', function ($ok, $product_id) {
    if (!$ok || !class_exists('WC_Product_Addons_Helper')) {
        return $ok;
    }
    $grupos = [];
    foreach ((array) WC_Product_Addons_Helper::get_product_addons($product_id) as $a) {
        if (empty($a['type']) || $a['type'] !== 'input_multiplier') {
            continue;
        }
        $g = dlp_fe_xg_marca(isset($a['description']) ? $a['description'] : '');
        if (!$g || empty($a['field_name'])) {
            continue;
        }
        $k = $g['titulo'];
        if (!isset($grupos[$k])) {
            $grupos[$k] = ['n' => $g['n'], 'suma' => 0];
        }
        $campo = 'addon-' . $a['field_name'];
        $v = isset($_POST[$campo]) ? (int) wp_unslash($_POST[$campo]) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        $grupos[$k]['suma'] += max(0, $v);
    }
    // Casillas con [elegir:min-max]: el número de marcadas debe estar entre el mínimo y el máximo
    foreach ((array) WC_Product_Addons_Helper::get_product_addons($product_id) as $a) {
        if (empty($a['type']) || $a['type'] !== 'checkbox' || empty($a['field_name']) || empty($a['description']) || !preg_match('/\[elegir:(\d+)(?:\s*-\s*(\d+))?\]/i', $a['description'], $e)) {
            continue;
        }
        $mn = (int) $e[1];
        $mx = isset($e[2]) && $e[2] !== '' ? (int) $e[2] : $mn;
        $campo = 'addon-' . $a['field_name'];
        $n     = isset($_POST[$campo]) ? count(array_filter((array) wp_unslash($_POST[$campo]))) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        if ($n < $mn || $n > $mx) {
            wc_add_notice(sprintf('%s: elige %s (llevas %d).', $a['name'], $mn === $mx ? "exactamente $mn" : "entre $mn y $mx", $n), 'error');
            return false;
        }
    }
    foreach ($grupos as $titulo => $g) {
        if ($g['suma'] !== $g['n']) {
            wc_add_notice(sprintf('%s: elige exactamente %d (llevas %d).', $titulo, $g['n'], $g['suma']), 'error');
            return false;
        }
    }
    return $ok;
}, 30, 2);
