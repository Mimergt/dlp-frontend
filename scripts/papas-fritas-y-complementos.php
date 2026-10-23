<?php
/**
 * Feedback del cliente (2026-10-23): (1) «Fries» pasa a «Papas fritas» en nombres, descripciones y extras; (2) en los combos con varios
 * acompañamientos (Felices los 4, Tríos, Las Favoritas) el cliente reparte los N acompañamientos como quiera (ej. 1 papa y 3 aros):
 * «Elige tus N complementos» = grupo `[grupo:Título=N]` con Papas fritas (incluida), Cheese poppers y Aros de cebolla (+Q10 c/u).
 * Solo dev. Idempotente. Copias: _dlp_textos_backup_papas y _product_addons_backup_complementos. Uso: wp eval-file papas-fritas-y-complementos.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$pf = function ($t) {
    $t = preg_replace('/\bFries\b/u', 'Papas fritas', (string) $t);
    return preg_replace('/\bfries\b/u', 'papas fritas', $t);
};

// ---- 1) Textos de productos
$n = 0;
$q = new WP_Query(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'posts_per_page' => -1, 'fields' => 'ids']);
foreach ($q->posts as $id) {
    $p = get_post($id);
    $nuevo = ['post_title' => $pf($p->post_title), 'post_excerpt' => $pf($p->post_excerpt), 'post_content' => $pf($p->post_content)];
    if ($nuevo['post_title'] === $p->post_title && $nuevo['post_excerpt'] === $p->post_excerpt && $nuevo['post_content'] === $p->post_content) { continue; }
    $n++;
    echo "texto $id [{$p->post_status}] " . html_entity_decode($p->post_title) . ' → ' . html_entity_decode($nuevo['post_title']) . "\n";
    if ($dry) { continue; }
    if (!get_post_meta($id, '_dlp_textos_backup_papas', true)) { update_post_meta($id, '_dlp_textos_backup_papas', wp_slash(wp_json_encode(['t' => $p->post_title, 'e' => $p->post_excerpt, 'c' => $p->post_content]))); }
    wp_update_post(wp_slash(['ID' => $id] + $nuevo));
}
echo "productos con texto cambiado: $n\n";

// ---- 2) Combos con varios acompañamientos: grupo de cantidades
$combos = [122472 => 4, 126123 => 3, 126125 => 3, 143397 => 4];
$base = (int) (microtime(true) * 10) % 1000000000; $k = 0;
foreach ($combos as $pid => $cant) {
    $a = get_post_meta($pid, '_product_addons', true);
    $a = is_array($a) ? $a : [];
    $idx = null;
    foreach ($a as $i => $x) {
        if (in_array($x['name'] ?? '', ['Complemento', 'Elige tu complemento'], true) && ($x['type'] ?? '') === 'multiple_choice') { $idx = $i; break; }
    }
    if ($idx === null) { echo "$pid: ya está convertido (o no tiene complemento)\n"; continue; }
    $titulo = "Elige tus $cant complementos";
    $fila = function ($nombre, $precio) use ($titulo, $cant, &$k, $base) {
        return ['name' => $nombre, 'title_format' => 'heading', 'description_enable' => 1, 'description' => "[grupo:$titulo=$cant]", 'placeholder_enable' => 0, 'placeholder' => '',
            'type' => 'input_multiplier', 'display' => 'select', 'position' => 0, 'required' => 0, 'restrictions' => 0, 'restrictions_type' => 'any_text',
            'adjust_price' => $precio === '' ? 0 : 1, 'price_type' => 'quantity_based', 'price' => $precio, 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => 0, 'options' => []];
    };
    $filas = [$fila('Papas fritas', ''), $fila('Cheese poppers', '10'), $fila('Aros de cebolla', '10')];
    echo "complementos $pid " . html_entity_decode(get_the_title($pid)) . ": $titulo (Papas fritas incluida; poppers y aros +Q10 c/u)\n";
    if ($dry) { continue; }
    if (!get_post_meta($pid, '_product_addons_backup_complementos', true)) { update_post_meta($pid, '_product_addons_backup_complementos', $a); }
    array_splice($a, $idx, 1, $filas);
    foreach ($a as $i => $x) { $a[$i]['position'] = $i; }
    update_post_meta($pid, '_product_addons', $a);
}

// ---- 3) «Fries» en el resto de extras (productos y extras globales)
$m = 0;
$q = new WP_Query(['post_type' => ['product', 'global_product_addon'], 'post_status' => ['publish', 'draft', 'private', 'pending'], 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => '_product_addons']);
foreach ($q->posts as $pid) {
    $a = get_post_meta($pid, '_product_addons', true);
    if (!is_array($a)) { continue; }
    $cambio = false;
    foreach ($a as $i => $x) {
        foreach (['name', 'description'] as $f) { if (isset($x[$f]) && is_string($x[$f]) && preg_match('/fries/i', $x[$f])) { $a[$i][$f] = $pf($x[$f]); $cambio = true; } }
        foreach ((array) ($x['options'] ?? []) as $j => $o) { if (!empty($o['label']) && preg_match('/fries/i', $o['label'])) { $a[$i]['options'][$j]['label'] = $pf($o['label']); $cambio = true; } }
    }
    if ($cambio) {
        $m++; echo "extras $pid " . html_entity_decode(get_the_title($pid)) . "\n";
        if (!$dry) {
            if (!get_post_meta($pid, '_product_addons_backup_papas', true)) { update_post_meta($pid, '_product_addons_backup_papas', get_post_meta($pid, '_product_addons', true)); }
            update_post_meta($pid, '_product_addons', $a);
        }
    }
}
echo ($dry ? '[dry] ' : '') . "productos con extras cambiados: $m\n";
