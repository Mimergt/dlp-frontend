<?php
/**
 * Agrega la opción «Papas Grandes» (+Q5) a todos los grupos de complemento que tienen «Papas fritas» (combos, tríos, Felices los 4…).
 * Uso:  wp eval-file scripts/papas-grandes.php [dry|aplicar|revertir]
 *  - dry (por defecto): solo lista lo que cambiaría.
 *  - aplicar: guarda un respaldo de cada producto en la opción `dlp_papas_grandes_respaldo` y agrega la opción justo después de «Papas fritas».
 *  - revertir: restaura el respaldo.
 * Es idempotente (no duplica) y no usa IDs fijos: sirve igual en producción.
 */
$modo   = $args[0] ?? 'dry';
$LABEL  = 'Papas Grandes';
$PRECIO = '5';
global $wpdb;

if ($modo === 'revertir') {
    $resp = get_option('dlp_papas_grandes_respaldo', []);
    foreach ($resp as $id => $meta) {
        update_post_meta((int) $id, '_product_addons', $meta);
        echo "restaurado $id\n";
    }
    exit;
}

$ids = $wpdb->get_col("SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key='_product_addons' AND meta_value LIKE '%Papas fritas%'");
$resp = get_option('dlp_papas_grandes_respaldo', []);
$tocados = 0;
foreach ($ids as $id) {
    $p = get_post($id);
    if (!$p || $p->post_type !== 'product') {
        continue;
    }
    $a = get_post_meta($id, '_product_addons', true);
    if (!is_array($a)) {
        continue;
    }
    $nuevo = $a;
    $cambios = [];
    foreach ($a as $k => $g) {
        if (!is_array($g) || empty($g['options']) || !is_array($g['options'])) {
            continue;
        }
        $labels = array_map(function ($o) { return is_array($o) ? trim($o['label'] ?? '') : ''; }, $g['options']);
        $pos = array_search('Papas fritas', $labels, true);
        if ($pos === false || in_array($LABEL, $labels, true)) {
            continue;
        }
        $base = $g['options'][$pos];
        $op = $base;
        $op['label'] = $LABEL;
        $op['price'] = $PRECIO;
        $op['image'] = '';
        $op['visibility'] = 1;
        array_splice($nuevo[$k]['options'], $pos + 1, 0, [$op]);
        $cambios[] = '«' . ($g['name'] ?? '?') . '» (' . ($g['type'] ?? '?') . ')';
    }
    if (!$cambios) {
        continue;
    }
    $tocados++;
    echo "$id {$p->post_status} {$p->post_title} → " . implode(', ', $cambios) . "\n";
    if ($modo === 'aplicar') {
        if (!isset($resp[$id])) {
            $resp[$id] = $a;
        }
        update_post_meta($id, '_product_addons', $nuevo);
    }
}
if ($modo === 'aplicar') {
    update_option('dlp_papas_grandes_respaldo', $resp, false);
    wc_delete_product_transients();
}
echo "$modo: $tocados productos\n";
