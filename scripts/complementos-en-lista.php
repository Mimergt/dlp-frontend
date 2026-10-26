<?php
/**
 * Feedback del cliente (2026-10-26): los complementos no se pueden manejar con botones − / + (Felices los 4). Quedan como una
 * lista por complemento, agrupadas en un acordeón «Elige tus N complementos» con contador y Obligatorio.
 *  - Felices los 4 (4), Trío de Clásicas (3), Trío Del Puente (3), Las Favoritas (4): las 3 filas de cantidad pasan a N listas
 *    «Complemento 1…N» con Papas fritas (incluidas), Cheese poppers y Aros de cebolla (+Q10 c/u).
 *  - Carnuda Lovers: «Complemento 1» y «Complemento 2» se agrupan en el mismo acordeón (las listas no cambian).
 * Solo dev. Idempotente. Copia previa en _product_addons_backup_cantidades. Uso: wp eval-file complementos-en-lista.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$combos = [122472 => 4, 126123 => 3, 126125 => 3, 143397 => 4];
$base = (int) (microtime(true) * 10) % 1000000000; $k = 0;

foreach ($combos as $pid => $cant) {
    $a = get_post_meta($pid, '_product_addons', true);
    $a = is_array($a) ? $a : [];
    $titulo = "Elige tus $cant complementos";
    $idx = [];
    foreach ($a as $i => $x) {
        if (($x['type'] ?? '') === 'input_multiplier' && strpos((string) ($x['description'] ?? ''), "[grupo:$titulo=$cant]") !== false) { $idx[] = $i; }
    }
    if (!$idx) { echo "$pid: sin filas de cantidad (ya convertido)\n"; continue; }
    $listas = [];
    for ($n = 1; $n <= $cant; $n++) {
        $listas[] = ['name' => "Complemento $n", 'title_format' => 'heading', 'description_enable' => 1, 'description' => "[acordeon:$titulo]", 'placeholder_enable' => 0, 'placeholder' => '',
            'type' => 'multiple_choice', 'display' => 'select', 'position' => 0, 'required' => 1, 'restrictions' => 0, 'restrictions_type' => 'any_text',
            'adjust_price' => 0, 'price_type' => 'flat_fee', 'price' => '', 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => 0,
            'options' => [
                ['label' => 'Papas fritas', 'price' => '', 'image' => '', 'price_type' => 'flat_fee', 'visibility' => 1],
                ['label' => 'Cheese poppers', 'price' => '10', 'image' => '', 'price_type' => 'flat_fee', 'visibility' => 1],
                ['label' => 'Aros de cebolla', 'price' => '10', 'image' => '', 'price_type' => 'flat_fee', 'visibility' => 1],
            ]];
    }
    echo "$pid " . html_entity_decode(get_the_title($pid)) . ": " . count($idx) . " filas de cantidad → $cant listas (acordeón «{$titulo}»)\n";
    if ($dry) { continue; }
    if (!get_post_meta($pid, '_product_addons_backup_cantidades', true)) { update_post_meta($pid, '_product_addons_backup_cantidades', $a); }
    $first = $idx[0];
    $nuevo = [];
    foreach ($a as $i => $x) {
        if ($i === $first) { foreach ($listas as $l) { $nuevo[] = $l; } }
        if (in_array($i, $idx, true)) { continue; }
        $nuevo[] = $x;
    }
    foreach ($nuevo as $i => $x) { $nuevo[$i]['position'] = $i; }
    update_post_meta($pid, '_product_addons', $nuevo);
}

// Carnuda Lovers
$a = get_post_meta(143735, '_product_addons', true);
$hecho = 0;
if (is_array($a)) {
    foreach ($a as $i => $x) {
        if (in_array($x['name'] ?? '', ['Complemento 1', 'Complemento 2'], true) && ($x['type'] ?? '') === 'multiple_choice' && strpos((string) $x['description'], '[acordeon:') === false) {
            $a[$i]['description_enable'] = 1;
            $a[$i]['description'] = '[acordeon:Elige tus 2 complementos]';
            $hecho++;
        }
    }
}
echo "Carnuda Lovers: $hecho listas agrupadas\n";
if (!$dry && $hecho) {
    if (!get_post_meta(143735, '_product_addons_backup_cantidades', true)) { update_post_meta(143735, '_product_addons_backup_cantidades', get_post_meta(143735, '_product_addons', true)); }
    update_post_meta(143735, '_product_addons', $a);
}
