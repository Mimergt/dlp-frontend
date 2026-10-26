<?php
/**
 * Estándar de promos: las opciones se agrupan POR PRODUCTO (acordeón «<Producto N>» con su complemento y sus ingredientes por quitar),
 * después las bebidas incluidas (si las hay) y «Añade bebida extra» con precio. La carne favorita (si existe) queda arriba.
 * Solo dev. Idempotente (si ya está convertido no hace nada). Copia previa en _product_addons_backup_estandar.
 * Uso: wp eval-file promo-estandar.php <id> [dry]    Productos: 122472 Felices los 4 · 126123 Trío de Clásicas · 126125 Trío Del Puente · 143397 Las Favoritas
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$pid = (int) ($args[0] ?? 0);
// Hamburguesas de cada promo, en el orden en que se emparejan con «Complemento 1…N». Las casillas existentes se llaman «<nombre>: quitar ingredientes».
$config = [
    122472 => ['burgers' => ['Del Puente 1', 'Del Puente 2', 'Clásica 1', 'Clásica 2']],
    126123 => ['burgers' => ['Clásica 1', 'Clásica 2', 'Clásica 3']],
    126125 => ['burgers' => ['Del Puente 1', 'Del Puente 2', 'Del Puente 3']],
    143397 => ['burgers' => ['La Clásica', 'Del Puente', 'Carnuda', 'Tejana']],
];
if (!isset($config[$pid])) { echo "producto no configurado\n"; return; }
$a = get_post_meta($pid, '_product_addons', true);
$a = is_array($a) ? $a : [];
$comp = []; $quitar = []; $otros = []; $beb = [];
foreach ($a as $x) {
    $n = $x['name'] ?? '';
    if (preg_match('/^Complemento (\d+)$/u', $n, $m) && ($x['type'] ?? '') === 'multiple_choice') { $comp[(int) $m[1]] = $x; continue; }
    if (preg_match('/^(.+): quitar ingredientes$/u', $n, $m) && ($x['type'] ?? '') === 'checkbox') { $quitar[$m[1]] = $x; continue; }
    if (($x['type'] ?? '') === 'input_multiplier' && preg_match('/\[grupo:([^=\]]+)=/u', (string) $x['description'], $g)) {
        if (strpos($g[1], 'extra') !== false) { echo "$pid: ya tiene «Añade bebida extra» (ya convertido)\n"; return; }
        $beb[] = $x; continue;
    }
    $otros[] = $x;
}
$burgers = $config[$pid]['burgers'];
if (count($comp) !== count($burgers) || array_diff($burgers, array_keys($quitar))) { echo "$pid: la estructura no coincide (complementos " . count($comp) . ", hamburguesas " . count($burgers) . "); se omite\n"; return; }

$base = (int) (microtime(true) * 10) % 1000000000; $k = 0;
$nuevo = $otros; // Carne favorita y demás, arriba
foreach ($burgers as $i => $b) {
    $c = $comp[$i + 1];
    $c['name'] = "$b: complemento"; $c['description_enable'] = 1; $c['description'] = "[acordeon:$b]"; $c['required'] = 1;
    $q = $quitar[$b];
    $q['description_enable'] = 1; $q['description'] = "Marca lo que NO quieres. [acordeon:$b]";
    $nuevo[] = $c; $nuevo[] = $q;
}
foreach ($beb as $x) { $nuevo[] = $x; } // bebidas incluidas (N de N, sin precio)
$precios = ['Frezka' => 17, 'Agua pura' => 9];
foreach ([['Frezka Tamarindo', 17], ['Frezka Rosa de Jamaica', 17], ['Frezka Sandía Limón & Maracuyá', 17], ['Agua pura', 9], ['Orange Crush', 11], ['Tiky', 11], ['Canada Dry Ginger Ale', 11], ['Coca Cola', 11], ['Coca Cola Zero', 11]] as [$nombre, $precio]) {
    $nuevo[] = ['name' => "Extra · $nombre", 'title_format' => 'heading', 'description_enable' => 1, 'description' => '[grupo:Añade bebida extra=*]', 'placeholder_enable' => 0, 'placeholder' => '',
        'type' => 'input_multiplier', 'display' => 'select', 'position' => 0, 'required' => 0, 'restrictions' => 0, 'restrictions_type' => 'any_text',
        'adjust_price' => 1, 'price_type' => 'quantity_based', 'price' => (string) $precio, 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => 0, 'options' => []];
}
foreach ($nuevo as $i => $x) { $nuevo[$i]['position'] = $i; }
echo "$pid " . html_entity_decode(get_the_title($pid)) . ': ' . count($a) . ' → ' . count($nuevo) . " extras (" . count($burgers) . " productos con complemento + ingredientes, bebidas incluidas, bebida extra con precio)\n";
if ($dry) { return; }
if (!get_post_meta($pid, '_product_addons_backup_estandar', true)) { update_post_meta($pid, '_product_addons_backup_estandar', $a); }
update_post_meta($pid, '_product_addons', $nuevo);
