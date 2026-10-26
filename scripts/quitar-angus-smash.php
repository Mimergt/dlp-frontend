<?php
/**
 * Feedback del cliente (2026-10-26): las SMASH (combos e individuales) no llevan la opción de elegir Angus, como la Clásica SMASH.
 * Quita el extra «Elige tu carne favorita» (Premium Blend / Si, 1 Torta Angus) de las SMASH que lo tengan. Solo dev. Idempotente.
 * Copia previa en _product_addons_backup_angus. Uso: wp eval-file quitar-angus-smash.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$q = new WP_Query(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => -1, 'fields' => 'ids', 's' => 'SMASH']);
foreach ($q->posts as $id) {
    $a = get_post_meta($id, '_product_addons', true);
    if (!is_array($a)) { continue; }
    $nuevo = [];
    foreach ($a as $x) {
        $opts = implode(' ', array_map(function ($o) { return $o['label'] ?? ''; }, (array) ($x['options'] ?? [])));
        if (($x['type'] ?? '') === 'multiple_choice' && stripos($opts, 'angus') !== false) { continue; }
        $nuevo[] = $x;
    }
    if (count($nuevo) === count($a)) { echo "#$id " . html_entity_decode(get_the_title($id)) . ": sin opción Angus\n"; continue; }
    foreach ($nuevo as $i => $x) { $nuevo[$i]['position'] = $i; }
    echo "#$id " . html_entity_decode(get_the_title($id)) . ": se quita " . (count($a) - count($nuevo)) . " extra(s) de Angus\n";
    if ($dry) { continue; }
    if (!get_post_meta($id, '_product_addons_backup_angus', true)) { update_post_meta($id, '_product_addons_backup_angus', $a); }
    update_post_meta($id, '_product_addons', $nuevo);
}
