<?php
/** Cambia «Curly Fries» por «Fries» en los extras (nombres, descripciones y opciones) de productos y extras globales. Uso: wp eval-file renombrar-curly-fries.php [dry]. Copia en el meta _product_addons_backup_fries. */
$dry   = in_array("dry", (array) ($args ?? []), true);
$cambios = 0; $prods = 0;
function dlp_fix($s) { return preg_replace('/curly\s+fries/i', 'Fries', $s); }

$ids = get_posts(['post_type' => ['product', 'global_product_addon'], 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids']);
foreach ($ids as $pid) {
    $a = get_post_meta($pid, '_product_addons', true);
    if (!is_array($a) || !$a) { continue; }
    $n = 0; $muestras = [];
    foreach ($a as $k => $x) {
        foreach (['name', 'description', 'placeholder'] as $f) {
            if (!empty($x[$f]) && is_string($x[$f]) && preg_match($re, $x[$f])) {
                $muestras[] = $x[$f] . ' → ' . dlp_fix($x[$f]);
                $a[$k][$f] = dlp_fix($x[$f]); $n++;
            }
        }
        foreach ((array) ($x['options'] ?? []) as $j => $o) {
            if (!empty($o['label']) && preg_match($re, $o['label'])) {
                $muestras[] = $o['label'] . ' → ' . dlp_fix($o['label']);
                $a[$k]['options'][$j]['label'] = dlp_fix($o['label']); $n++;
            }
        }
    }
    if (!$n) { continue; }
    $prods++; $cambios += $n;
    $p = get_post($pid);
    echo $pid . ' [' . $p->post_type . '/' . $p->post_status . '] ' . $p->post_title . ': ' . $n . " cambio(s) | " . implode(' ; ', array_slice($muestras, 0, 3)) . "\n";
    if (!$dry) {
        if (!get_post_meta($pid, '_product_addons_backup_fries', true)) {
            update_post_meta($pid, '_product_addons_backup_fries', get_post_meta($pid, '_product_addons', true));
        }
        update_post_meta($pid, '_product_addons', $a);
    }
}
echo ($dry ? 'PRUEBA: ' : 'APLICADO: ') . "$cambios cambio(s) en $prods producto(s)/extras globales\n";

// Otros lugares donde aparece (solo informativo)
global $wpdb;
$otros = $wpdb->get_results("SELECT ID, post_type, post_status, post_title FROM {$wpdb->posts} WHERE post_type IN ('product','product_variation','page') AND post_status <> 'trash' AND (post_title LIKE '%Curly Fries%' OR post_content LIKE '%Curly Fries%' OR post_excerpt LIKE '%Curly Fries%')");
echo "Otros textos con «Curly Fries» (títulos, descripciones, páginas): " . count($otros) . "\n";
foreach ($otros as $o) { echo '  ' . $o->ID . ' [' . $o->post_type . '/' . $o->post_status . '] ' . $o->post_title . "\n"; }
