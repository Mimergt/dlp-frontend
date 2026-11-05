<?php
/**
 * «Añade bebida extra» → «Agrega un Postre» en los combos estándar (wp eval-file scripts/extras-postres.php [dry]).
 * Cambia las filas «Extra · <bebida>» ([grupo:Añade bebida extra=*]) por «Extra · <postre>» con el precio de cada postre
 * ([grupo:Agrega un Postre=*]), en el mismo sitio. Copia previa en `_product_addons_backup_bebidaextra`.
 */
$dry = in_array('dry', $args ?? [], true) || in_array('dry', $GLOBALS['argv'] ?? [], true);
$postres = [['Tres Leches', 29], ['Chocolate Fudge', 29], ['Pie de Manzana', 29], ['Galleta Chispas de Chocolate', 15], ['Galleta Azúcar Canela', 15], ['Galleta Sprinkles', 15], ['Galleta Macadamia', 15]];
foreach ([122472, 126123, 126125, 143397, 143735] as $pid) {
    $a = get_post_meta($pid, '_product_addons', true);
    if (!is_array($a)) { continue; }
    $nuevo = []; $puesto = false; $k = 0; $base = (int) (microtime(true) * 10) % 900000000 + 100000000;
    foreach ($a as $x) {
        if (($x['type'] ?? '') === 'input_multiplier' && strpos((string) ($x['description'] ?? ''), '[grupo:Añade bebida extra=*]') !== false) {
            if ($puesto) { continue; }
            $puesto = true;
            foreach ($postres as [$nombre, $precio]) {
                $nuevo[] = ['name' => "Extra · $nombre", 'title_format' => 'heading', 'description_enable' => 1, 'description' => '[grupo:Agrega un Postre=*]', 'placeholder_enable' => 0, 'placeholder' => '',
                    'type' => 'input_multiplier', 'display' => 'select', 'position' => 0, 'required' => 0, 'restrictions' => 0, 'restrictions_type' => 'any_text',
                    'adjust_price' => 1, 'price_type' => 'quantity_based', 'price' => (string) $precio, 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => 0, 'options' => []];
            }
            continue;
        }
        $nuevo[] = $x;
    }
    foreach ($nuevo as $i => $x) { $nuevo[$i]['position'] = $i; }
    echo "$pid " . html_entity_decode(get_the_title($pid)) . ': ' . count($a) . ' → ' . count($nuevo) . ($puesto ? '' : ' (sin bebida extra)') . "\n";
    if ($dry || !$puesto) { continue; }
    if (!get_post_meta($pid, '_product_addons_backup_bebidaextra', true)) { update_post_meta($pid, '_product_addons_backup_bebidaextra', $a); }
    update_post_meta($pid, '_product_addons', $nuevo);
}
