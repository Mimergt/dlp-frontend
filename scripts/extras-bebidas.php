<?php
/**
 * Cambia el extra «Bebida» (lista desplegable) de uno o más productos por 9 filas de cantidad con total exacto de 3.
 * Uso (solo dev): wp eval-file extras-bebidas.php <id_producto> [<id_producto>...]
 * Guarda copia del meta anterior en _product_addons_backup_bebida. Si el producto ya tiene el grupo, no hace nada.
 */
$bebidas = [
    ['Frezka Rosa de Jamaica', 0], ['Frezka Tamarindo', 0], ['Frezka Sandía Limón & Maracuyá', 0],
    ['Coca Cola Zero', 5], ['Agua pura', 5], ['Tiky', 5], ['Orange Crush', 5], ['Canada Dry Ginger Ale', 5], ['Coca Cola', 5],
];
$marca = '[grupo:Elige tus 3 bebidas=3]';

foreach ($args as $pid) {
    $pid = (int) $pid;
    $a   = get_post_meta($pid, '_product_addons', true);
    if (!is_array($a)) {
        echo "$pid: sin extras\n";
        continue;
    }
    foreach ($a as $x) {
        if (!empty($x['description']) && strpos($x['description'], '[grupo:Elige tus 3 bebidas') !== false) {
            echo "$pid: ya tiene el grupo\n";
            continue 2;
        }
    }
    $pos = null;
    $nuevo = [];
    foreach ($a as $x) {
        if (isset($x['name'], $x['type']) && $x['name'] === 'Bebida' && $x['type'] === 'multiple_choice') {
            $pos = count($nuevo);
            continue;
        }
        $nuevo[] = $x;
    }
    if ($pos === null) {
        echo "$pid: no tiene «Bebida»\n";
        continue;
    }
    update_post_meta($pid, '_product_addons_backup_bebida', $a);
    $filas = [];
    $base  = (int) (microtime(true) * 10) % 1000000000;
    foreach ($bebidas as $i => $b) {
        $filas[] = [
            'name' => $b[0], 'title_format' => 'heading', 'description_enable' => 1, 'description' => $marca,
            'placeholder_enable' => 0, 'placeholder' => '', 'type' => 'input_multiplier', 'display' => 'select',
            'position' => 0, 'required' => 0, 'restrictions' => 1, 'restrictions_type' => 'any_text',
            'adjust_price' => $b[1] ? 1 : 0, 'price_type' => 'quantity_based', 'price' => $b[1] ? (string) $b[1] : '',
            'id' => (string) ($base + $i), 'min' => 0, 'max' => 3, 'default' => 0,
        ];
    }
    array_splice($nuevo, $pos, 0, $filas);
    foreach ($nuevo as $k => $x) {
        $nuevo[$k]['position'] = $k;
    }
    update_post_meta($pid, '_product_addons', $nuevo);
    echo "$pid: Bebida reemplazada por 9 filas (total exacto 3)\n";
}
