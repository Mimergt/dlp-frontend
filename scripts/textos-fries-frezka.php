<?php
/**
 * Textos de productos (feedback del cliente, 2026-10-23): «Curly Fries» pasa a «Fries» en nombre/descripciones de TODOS los productos
 * (publicados y borradores) y, en los productos PUBLICADOS, «bebida» pasa a «Frezka(s)». Carnuda Lovers: 2 Carnudas + 2 complementos.
 * Solo dev. Idempotente. Copia de los textos originales en el meta _dlp_textos_backup_fries. Uso: wp eval-file textos-fries-frezka.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$fries = function ($t) {
    $t = preg_replace('/Curly\s*Fries?\b/u', 'Fries', $t);
    $t = preg_replace('/curly\s*fries?\b/u', 'fries', $t);
    return preg_replace('/Curly\s*Frie\b/u', 'Fries', $t);
};
// Reemplazos de «bebida» → Frezka, solo en productos publicados (por ID, para no tocar nada más)
$frezka = [
    108988 => ['bebida natural' => 'Frezka'],
    124435 => ['bebida natural' => 'Frezka'],
    126123 => ['3 Bebidas Naturales' => '3 Frezkas'],
    126125 => ['3 Bebidas Naturales' => '3 Frezkas'],
    143397 => ['4 frezcas' => '4 Frezkas'],
    122458 => ['1 Bebida' => '1 Frezka'],
];
$n = 0;
$q = new WP_Query(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'posts_per_page' => -1, 'fields' => 'ids']);
foreach ($q->posts as $id) {
    $p = get_post($id);
    $nuevo = ['post_title' => $p->post_title, 'post_excerpt' => $p->post_excerpt, 'post_content' => $p->post_content];
    foreach ($nuevo as $k => $v) {
        $v = $fries($v);
        if (isset($frezka[$id])) { $v = strtr($v, $frezka[$id]); }
        $nuevo[$k] = $v;
    }
    if ($id === 143735) { $nuevo['post_excerpt'] = $nuevo['post_content'] = '2 Carnudas + 2 complementos (Fries incluidas; Cheese poppers o Aros de cebolla +Q10 c/u).'; }
    if ($nuevo['post_title'] === $p->post_title && $nuevo['post_excerpt'] === $p->post_excerpt && $nuevo['post_content'] === $p->post_content) { continue; }
    $n++;
    echo "$id [{$p->post_status}] " . html_entity_decode($p->post_title) . "\n";
    foreach ($nuevo as $k => $v) { if ($v !== $p->$k) { echo "   $k: " . preg_replace('/\s+/', ' ', mb_substr(strip_tags($v), 0, 160)) . "\n"; } }
    if ($dry) { continue; }
    if (!get_post_meta($id, '_dlp_textos_backup_fries', true)) { update_post_meta($id, '_dlp_textos_backup_fries', wp_slash(wp_json_encode(['t' => $p->post_title, 'e' => $p->post_excerpt, 'c' => $p->post_content]))); }
    wp_update_post(wp_slash(['ID' => $id] + $nuevo));
}
echo ($dry ? '[dry] ' : '') . "productos actualizados: $n\n";

// Carnuda Lovers: dos complementos (cada uno: Fries incluida, o Cheese poppers / Aros de cebolla +Q10)
$pid = 143735;
$a = get_post_meta($pid, '_product_addons', true);
$a = is_array($a) ? $a : [];
$tiene2 = false;
foreach ($a as $x) { if (($x['name'] ?? '') === 'Complemento 2') { $tiene2 = true; } }
if (!$tiene2 && !$dry) {
    if (!get_post_meta($pid, '_product_addons_backup_2papas', true)) { update_post_meta($pid, '_product_addons_backup_2papas', $a); }
    $base = (int) (microtime(true) * 10) % 1000000000;
    foreach ($a as $i => $x) {
        if (($x['name'] ?? '') === 'Complemento') {
            $a[$i]['name'] = 'Complemento 1';
            $dos = $x; $dos['name'] = 'Complemento 2'; $dos['id'] = (string) ($base + 1);
            array_splice($a, $i + 1, 0, [$dos]);
            break;
        }
    }
    foreach ($a as $i => $x) { $a[$i]['position'] = $i; }
    update_post_meta($pid, '_product_addons', $a);
    echo "Carnuda Lovers: " . implode(' | ', array_column($a, 'name')) . "\n";
} elseif ($tiene2) { echo "Carnuda Lovers: ya tiene 2 complementos\n"; }
