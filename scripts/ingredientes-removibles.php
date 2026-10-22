<?php
/**
 * Agrega el extra «quitar ingredientes» (casillas «Sin X») con el acordeón [acordeon:Quitar ingredientes] a los productos
 * de INGREDIENTES REMOVIBLES.xlsx que existen en el sitio. Solo dev. Idempotente: si el producto ya tiene un extra con
 * [acordeon:...] no hace nada. Guarda copia en el meta _product_addons_backup_ingredientes.
 * Uso: wp eval-file ingredientes-removibles.php [--dry]   (con «dry» solo muestra lo que haría)
 */
$dry = in_array('dry', (array) ($args ?? []), true) || in_array('--dry', (array) ($args ?? []), true);

// Listas por hamburguesa (tomadas del Excel, filas de Individuales y Combos)
$L = [
    'DP'  => ['Queso Americano', 'Tomate', 'Lechuga', 'Cebolla caramelizada', 'Champiñones salteados', 'Tocino'],
    'CL'  => ['Queso Americano', 'Tomate', 'Lechuga', 'Cebolla', 'Salsa'],
    'TO'  => ['Queso Americano', 'Tomate', 'Lechuga', 'Cebolla', 'Tocino', 'Huevo'],
    'TJ'  => ['Queso Americano', 'Lechuga', 'Onion tanglers', 'Jalapeños', 'BBQ'],
    'BC'  => ['Queso Cheddar', 'Tocino'],
    'SM'  => ['Queso Suizo', 'Champiñones salteados'],
    'CA'  => ['Tomate', 'Lechuga', 'Cebolla', 'Salsa'],
    'INF' => ['Queso Americano', 'Lechuga'],
];

// ID del producto en el sitio => lista de extras [nombre del extra, lista]. Un solo extra = una hamburguesa.
$P = [
    // Individuales
    2131   => [['Ingredientes', 'DP']],  2139 => [['Ingredientes', 'CL']], 2135 => [['Ingredientes', 'TO']], 2137 => [['Ingredientes', 'TJ']],
    124437 => [['Ingredientes', 'BC']],  124441 => [['Ingredientes', 'SM']], 2141 => [['Ingredientes', 'INF']],
    // Combos
    2049   => [['Ingredientes', 'DP']],  2057 => [['Ingredientes', 'CL']], 2053 => [['Ingredientes', 'TO']], 2055 => [['Ingredientes', 'TJ']],
    124430 => [['Ingredientes', 'BC']],  124435 => [['Ingredientes', 'SM']], 2059 => [['Ingredientes', 'INF']],
    // Promos con varias hamburguesas: un extra por cada una
    122472 => [['Del Puente 1: quitar ingredientes', 'DP'], ['Del Puente 2: quitar ingredientes', 'DP'], ['Clásica 1: quitar ingredientes', 'CL'], ['Clásica 2: quitar ingredientes', 'CL']],
    126123 => [['Clásica 1: quitar ingredientes', 'CL'], ['Clásica 2: quitar ingredientes', 'CL'], ['Clásica 3: quitar ingredientes', 'CL']],
    126125 => [['Del Puente 1: quitar ingredientes', 'DP'], ['Del Puente 2: quitar ingredientes', 'DP'], ['Del Puente 3: quitar ingredientes', 'DP']],
    143397 => [['La Clásica: quitar ingredientes', 'CL'], ['Del Puente: quitar ingredientes', 'DP'], ['Carnuda: quitar ingredientes', 'CA'], ['Tejana: quitar ingredientes', 'TJ']],
];

$marca = ' [acordeon:Quitar ingredientes]';
$base  = (int) (microtime(true) * 10) % 1000000000;
$k     = 0;
foreach ($P as $pid => $extras) {
    $post = get_post($pid);
    if (!$post || $post->post_type !== 'product') {
        echo "$pid: no existe\n";
        continue;
    }
    $a = get_post_meta($pid, '_product_addons', true);
    $a = is_array($a) ? $a : [];
    foreach ($a as $x) {
        if (!empty($x['description']) && strpos($x['description'], '[acordeon:') !== false) {
            echo "$pid {$post->post_title}: ya tiene acordeón, se omite\n";
            continue 2;
        }
    }
    foreach ($extras as $e) {
        $opciones = [];
        foreach ($L[$e[1]] as $ing) {
            $opciones[] = ['label' => 'Sin ' . $ing, 'price' => '', 'image' => '', 'price_type' => 'flat_fee', 'visibility' => 1];
        }
        $a[] = [
            'name' => $e[0], 'title_format' => 'heading', 'description_enable' => 1,
            'description' => 'Marca lo que NO quieres.' . $marca, 'placeholder_enable' => 0, 'placeholder' => '',
            'type' => 'checkbox', 'display' => 'select', 'position' => count($a), 'required' => 0, 'restrictions' => 0,
            'restrictions_type' => 'any_text', 'adjust_price' => 0, 'price_type' => 'flat_fee', 'price' => '',
            'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => '', 'options' => $opciones,
        ];
    }
    if ($dry) {
        echo "$pid {$post->post_title} [{$post->post_status}]: agregaría " . count($extras) . " extra(s)\n";
        continue;
    }
    if (!get_post_meta($pid, '_product_addons_backup_ingredientes', true)) {
        update_post_meta($pid, '_product_addons_backup_ingredientes', get_post_meta($pid, '_product_addons', true) ?: []);
    }
    update_post_meta($pid, '_product_addons', $a);
    echo "$pid {$post->post_title} [{$post->post_status}]: " . count($extras) . " extra(s) agregados\n";
}
