<?php
/**
 * Reordena (y nombra) los extras «quitar ingredientes» según la hoja «detalle orden» de INGREDIENTES REMOVIBLES (1).xlsx:
 * los ingredientes van en el orden de la receta de cada hamburguesa. Solo dev. Idempotente. Copia en el meta
 * _product_addons_backup_orden_receta. Uso: wp eval-file ingredientes-orden-receta.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);

// Orden de la receta (hoja «detalle orden»); el texto en minúsculas salvo la primera letra, como el resto de extras.
$L = [
    'DP'  => ['Queso Americano', 'Cebolla caramelizada', 'Champiñones salteados', 'Tocino', 'Tomate', 'Lechuga'],
    'CL'  => ['Queso Americano', 'Salsa de la casa', 'Tomate', 'Cebolla', 'Lechuga'],
    'TO'  => ['Queso Americano', 'Huevo estrellado', 'Tocino', 'Tomate', 'Cebolla', 'Lechuga'],
    'TJ'  => ['Queso Americano', 'Salsa BBQ', 'Onion tanglers', 'Jalapeños', 'Lechuga'],
    'BC'  => ['Queso Cheddar', 'Tocino'],
    'SM'  => ['Queso Suizo', 'Champiñones salteados'],
    'CA'  => ['Salsa Ajo Cilantro', 'Tomate', 'Cebolla', 'Lechuga'],
    'LEG' => ['Lascas de cebolla salteadas', 'Lascas de jalapeño salteadas', 'Salsa Legendaria', 'Queso Americano'],
    'CLS' => ['Lechuga', 'Queso Americano', 'Salsa de la casa', 'Tomate', 'Cebolla'],
    'JP'  => ['Queso crema', 'Lascas de cebolla salteadas', 'Lascas de jalapeño salteadas', 'Queso Americano', 'Jalea de frambuesa'],
    'POL' => ['Lechuga', 'Pepinillos', 'Salsa'],
    'INF' => ['Queso Americano', 'Lechuga'],
];
// Producto => hamburguesa (un solo extra de ingredientes)
$P = [
    2131 => 'DP', 2049 => 'DP', 2139 => 'CL', 2057 => 'CL', 122470 => 'CL', 2135 => 'TO', 2053 => 'TO', 122461 => 'TO', 2137 => 'TJ', 2055 => 'TJ',
    124437 => 'BC', 124430 => 'BC', 124441 => 'SM', 124435 => 'SM', 2141 => 'INF', 2059 => 'INF', 108988 => 'CA',
    101954 => 'POL', 101950 => 'POL', 101946 => 'POL', 101944 => 'POL', 101942 => 'POL', 101940 => 'POL',
    159000 => 'CLS', 158996 => 'CLS', 159004 => 'LEG', 158998 => 'LEG', 159002 => 'JP', 159006 => 'JP',
];
// Productos con varias hamburguesas: el nombre del extra empieza por la hamburguesa
$por_nombre = ['Del Puente' => 'DP', 'La Clásica' => 'CL', 'Clásica' => 'CL', 'Carnuda' => 'CA', 'Tejana' => 'TJ'];
$MULTI = [143735, 143397, 126125, 126123, 122472];

$cambios = 0;
foreach (array_merge(array_keys($P), $MULTI) as $pid) {
    $a = get_post_meta($pid, '_product_addons', true);
    if (!is_array($a)) { echo "$pid: sin extras\n"; continue; }
    $hecho = false;
    foreach ($a as $i => $x) {
        $d = $x['description'] ?? '';
        $n = $x['name'] ?? '';
        $es_ing = strpos($d, '[acordeon:Quitar') !== false || stripos($n, 'quitar ingredientes') !== false || $n === 'Ingredientes';
        if (!$es_ing || ($x['type'] ?? '') !== 'checkbox') { continue; }
        $code = $P[$pid] ?? null;
        if (!$code) {
            foreach ($por_nombre as $pref => $c) { if (stripos($n, $pref) === 0) { $code = $c; break; } }
            if ($pid === 143735) { $code = 'CA'; }
        }
        if (!$code || !isset($L[$code])) { echo "$pid «$n»: sin hamburguesa asignada, se omite\n"; continue; }
        $nuevo = array_map(function ($ing) { return ['label' => 'Sin ' . $ing, 'price' => '', 'image' => '', 'price_type' => 'flat_fee', 'visibility' => 1]; }, $L[$code]);
        $antes = implode(', ', array_column($x['options'] ?? [], 'label'));
        $desp  = implode(', ', array_column($nuevo, 'label'));
        if ($antes === $desp) { continue; }
        echo "$pid " . html_entity_decode(get_the_title($pid)) . " «{$n}» [$code]\n   antes:   $antes\n   después: $desp\n";
        $a[$i]['options'] = $nuevo;
        $hecho = true; $cambios++;
    }
    if ($hecho && !$dry) {
        if (!get_post_meta($pid, '_product_addons_backup_orden_receta', true)) { update_post_meta($pid, '_product_addons_backup_orden_receta', get_post_meta($pid, '_product_addons', true)); }
        update_post_meta($pid, '_product_addons', $a);
    }
}
echo ($dry ? '[dry] ' : '') . "extras actualizados: $cambios\n";
