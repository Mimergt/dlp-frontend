<?php
/**
 * Estándar de promos (feedback 2026-10-26): las opciones se agrupan POR PRODUCTO. Piloto: Carnuda Lovers (2 carnudas + 2 complementos, sin bebida).
 *   Carnuda 1 → complemento + quitar ingredientes   (acordeón «Carnuda 1»)
 *   Carnuda 2 → complemento + quitar ingredientes   (acordeón «Carnuda 2»)
 *   Añade bebida extra → filas − 0 + con precio (grupo libre, mismos precios de los kits: Frezka Q17, Agua Q9, resto Q11)
 * Solo dev. Idempotente. Copia previa en _product_addons_backup_estandar. Uso: wp eval-file promo-carnuda-estandar.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$pid = 143735;
$a = get_post_meta($pid, '_product_addons', true);
if (!is_array($a)) { echo "sin extras\n"; return; }
$byName = [];
foreach ($a as $x) { $byName[$x['name']] = $x; }
if (!isset($byName['Complemento 1'], $byName['Complemento 2'], $byName['Carnuda 1: quitar ingredientes'], $byName['Carnuda 2: quitar ingredientes'])) { echo "ya está en el estándar (o cambió la estructura)\n"; return; }

$base = (int) (microtime(true) * 10) % 1000000000; $k = 0;
$comp = function ($n) use ($byName, $base, &$k) {
    $x = $byName["Complemento $n"];
    $x['name'] = "Carnuda $n: complemento";
    $x['description_enable'] = 1;
    $x['description'] = "[acordeon:Carnuda $n]";
    $x['required'] = 1;
    return $x;
};
$quitar = function ($n) use ($byName) {
    $x = $byName["Carnuda $n: quitar ingredientes"];
    $x['description_enable'] = 1;
    $x['description'] = "Marca lo que NO quieres. [acordeon:Carnuda $n]";
    return $x;
};
$bebidas = [['Frezka Tamarindo', 17], ['Frezka Rosa de Jamaica', 17], ['Frezka Sandía Limón & Maracuyá', 17], ['Agua pura', 9], ['Orange Crush', 11], ['Tiky', 11], ['Canada Dry Ginger Ale', 11], ['Coca Cola', 11], ['Coca Cola Zero', 11]];
$filas = [];
foreach ($bebidas as [$nombre, $precio]) {
    $filas[] = ['name' => $nombre, 'title_format' => 'heading', 'description_enable' => 1, 'description' => '[grupo:Añade bebida extra=*]', 'placeholder_enable' => 0, 'placeholder' => '',
        'type' => 'input_multiplier', 'display' => 'select', 'position' => 0, 'required' => 0, 'restrictions' => 0, 'restrictions_type' => 'any_text',
        'adjust_price' => 1, 'price_type' => 'quantity_based', 'price' => (string) $precio, 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => 0, 'options' => []];
}
$nuevo = array_merge([$comp(1), $quitar(1), $comp(2), $quitar(2)], $filas);
foreach ($nuevo as $i => $x) { $nuevo[$i]['position'] = $i; }
echo "Carnuda Lovers: " . count($a) . " extras → " . count($nuevo) . " (2 productos con complemento + ingredientes y 9 bebidas extra con precio)\n";
if ($dry) { return; }
if (!get_post_meta($pid, '_product_addons_backup_estandar', true)) { update_post_meta($pid, '_product_addons_backup_estandar', $a); }
update_post_meta($pid, '_product_addons', $nuevo);
