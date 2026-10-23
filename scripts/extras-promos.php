<?php
/**
 * Genera los extras (plugin de extras de WooCommerce) de las promos y productos nuevos a partir de la configuración de
 * producción (WooFood) + las convenciones del plugin: grupo «elige N bebidas» ([grupo:...=N]) y acordeón de ingredientes
 * ([acordeon:Quitar ingredientes]). REEMPLAZA los extras del producto (copia en _product_addons_backup_promos).
 * Solo dev. Uso: wp eval-file extras-promos.php [dry] [id id ...]   (con IDs, solo procesa esos productos)
 */
$dry  = in_array('dry', (array) ($args ?? []), true);
$solo = array_filter(array_map('intval', (array) ($args ?? [])));   // si se pasan IDs, solo esos productos

$ING = [
    'DP' => ['Queso Americano', 'Tomate', 'Lechuga', 'Cebolla caramelizada', 'Champiñones salteados', 'Tocino'],
    'CL' => ['Queso Americano', 'Tomate', 'Lechuga', 'Cebolla', 'Salsa'],
    'TO' => ['Queso Americano', 'Tomate', 'Lechuga', 'Cebolla', 'Tocino', 'Huevo'],
    'TJ' => ['Queso Americano', 'Lechuga', 'Onion tanglers', 'Jalapeños', 'BBQ'],
    'CA' => ['Tomate', 'Lechuga', 'Cebolla', 'Salsa'],
    'BC' => ['Queso Cheddar', 'Tocino'],
    'SM' => ['Queso Suizo', 'Champiñones salteados'],
    'INF' => ['Queso Americano', 'Lechuga'],
    'PO' => ['Pepinillos', 'Lechuga', 'Salsa'],
    'SCL' => ['Queso Americano', 'Tomate', 'Lechuga', 'Lascas de cebolla salteadas', 'Salsa'],
    'SLG' => ['Lascas de cebolla salteadas', 'Lascas de jalapeño salteadas', 'Salsa Legendaria', 'Queso Americano'],
    'SJP' => ['Queso Americano', 'Lascas de cebolla salteadas', 'Lascas de jalapeño salteadas', 'Jalea de frambuesa', 'Queso crema'],
];
$BEB = [['Frezka Rosa de Jamaica', 0], ['Frezka Tamarindo', 0], ['Frezka Sandía Limón & Maracuyá', 0], ['Coca Cola Zero', 5], ['Agua pura', 5], ['Tiky', 5], ['Orange Crush', 5], ['Canada Dry Ginger Ale', 5], ['Coca Cola', 5]];

// n = combos que lleva la promo; hamb = hamburguesas [(nombre del extra, lista)]; carne/comp: 'n' (promo) o 1 (individual); beb: 'grupo' N | 'lista' | null
$S = [
    143397 => ['n' => 4, 'carne' => 4, 'comp' => 4, 'beb' => ['grupo', 4], 'hamb' => [['La Clásica: quitar ingredientes', 'CL'], ['Del Puente: quitar ingredientes', 'DP'], ['Carnuda: quitar ingredientes', 'CA'], ['Tejana: quitar ingredientes', 'TJ']]],
    126125 => ['carne' => 3, 'comp' => 3, 'beb' => ['grupo', 3], 'hamb' => [['Del Puente 1: quitar ingredientes', 'DP'], ['Del Puente 2: quitar ingredientes', 'DP'], ['Del Puente 3: quitar ingredientes', 'DP']]],
    126123 => ['carne' => 3, 'comp' => 3, 'beb' => ['grupo', 3], 'hamb' => [['Clásica 1: quitar ingredientes', 'CL'], ['Clásica 2: quitar ingredientes', 'CL'], ['Clásica 3: quitar ingredientes', 'CL']]],
    122461 => ['carne' => 1, 'hamb' => [['Ingredientes', 'TO']]],
    122470 => ['carne' => 1, 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'CL']]],   // La Clásica + Frezca: elige su bebida como La Carnuda
    122458 => ['beb' => ['grupo', 1]],
    122456 => [],
    108988 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'CA']]],
    158996 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'SCL']]],   // Combo Clásica Smash
    158998 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'SLG']]],   // Combo Legendaria Smash
    159006 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'SJP']]],   // Combo Jalapeño Popper Smash
    159000 => ['hamb' => [['Ingredientes', 'SCL']]],                                          // Clásica Smash individual
    159002 => ['carne' => 1, 'hamb' => [['Ingredientes', 'SJP']]],                            // Jalapeño Popper Smash individual
    159004 => ['carne' => 1, 'hamb' => [['Ingredientes', 'SLG']]],                            // Legendaria Smash individual
    159008 => [],                                                                              // Tenders infantil individual
    159010 => ['comp' => 'combo', 'beb' => ['lista']],                                        // Combo Tenders infantil
    // Combos de res clásicos (carne favorita + bebida + complemento + ingredientes)
    2057   => ['carne' => 1, 'comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'CL']]],
    2049   => ['carne' => 1, 'comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'DP']]],
    2055   => ['carne' => 1, 'comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'TJ']]],
    2053   => ['carne' => 1, 'comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'TO']]],
    2059   => ['carne' => 1, 'comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'INF']]],
    // Combos de pollo (bebida + complemento; los de pechuga con pepinillos/lechuga/salsa para quitar)
    101944 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'PO']]],   // Honey Mustard
    101940 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'PO']]],   // Ranch
    101954 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'PO']]],   // Chipotle
    101958 => ['comp' => 'combo', 'beb' => ['lista']],                                        // Tenders
    // Combos sin elección de carne
    124430 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'BC']]],
    124435 => ['comp' => 'combo', 'beb' => ['lista'], 'hamb' => [['Ingredientes', 'SM']]],
    2061   => ['comp' => 'combo', 'beb' => ['lista']],                                        // Combo Derretido
];

$base = (int) (microtime(true) * 10) % 1000000000;
$k    = 0;
$nuevo = function ($name, $type, $o = []) use (&$k, $base) {
    return array_merge([
        'name' => $name, 'title_format' => 'heading', 'description_enable' => 0, 'description' => '', 'placeholder_enable' => 0, 'placeholder' => '',
        'type' => $type, 'display' => 'select', 'position' => 0, 'required' => 0, 'restrictions' => 0, 'restrictions_type' => 'any_text',
        'adjust_price' => 0, 'price_type' => 'flat_fee', 'price' => '', 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => '',
    ], $o);
};
$opt = function ($label, $precio) { return ['label' => $label, 'price' => $precio ? (string) $precio : '', 'image' => '', 'price_type' => 'quantity_based', 'visibility' => 1]; };

foreach ($S as $pid => $r) {
    if ($solo && !in_array($pid, $solo, true)) { continue; }
    $post = get_post($pid);
    if (!$post || $post->post_type !== 'product') { echo "$pid: no existe\n"; continue; }
    $a = [];
    if (!empty($r['carne'])) {
        $n = $r['carne'];
        $lab = $n === 1 ? 'Si, 1 Torta Angus' : "Si, $n Tortas Angus";
        $a[] = $nuevo('Elige tu carne favorita', 'multiple_choice', ['required' => 1, 'options' => [$opt('Premium Blend', 0), $opt($lab, 12 * $n)]]);
    }
    $bloques = ['comp' => function () use (&$a, $r, $nuevo, $opt) {
        if (!empty($r['comp'])) {
        if ($r['comp'] === 'combo') {
            $a[] = $nuevo('Elige tu complemento', 'multiple_choice', ['required' => 1, 'options' => [$opt('Papas fritas', 0), $opt('Cheese poppers', 10), $opt('Aros de cebolla', 10)]]);
        } else {
            $n = $r['comp'];
            $a[] = $nuevo('Elige tu complemento', 'multiple_choice', ['required' => 1, 'options' => [$opt("$n Fries", 0), $opt("$n Cheese poppers", 10 * $n), $opt("$n Aros de cebolla", 10 * $n)]]);
        }
    }
    }, 'beb' => function () use (&$a, $r, $nuevo, $opt, $BEB) {
        if (!empty($r['beb'])) {
        if ($r['beb'][0] === 'lista') {
            $o = [];
            foreach ($BEB as $b) { $o[] = $opt($b[0], $b[1]); }
            $a[] = $nuevo('Elige tu bebida', 'multiple_choice', ['required' => 1, 'options' => $o]);
        } else {
            $N = $r['beb'][1];
            $titulo = $N === 1 ? 'Elige tu bebida' : "Elige tus $N bebidas";
            foreach ($BEB as $b) {
                $a[] = $nuevo($b[0], 'input_multiplier', [
                    'description_enable' => 1, 'description' => "[grupo:$titulo=$N]", 'restrictions' => 1, 'adjust_price' => $b[1] ? 1 : 0,
                    'price_type' => 'quantity_based', 'price' => $b[1] ? (string) $b[1] : '', 'min' => 0, 'max' => $N, 'default' => 0,
                ]);
            }
        }
    }
    }];
    $orden = (($r['comp'] ?? '') === 'combo') ? ['beb', 'comp'] : ['comp', 'beb'];
    foreach ($orden as $bk) { $bloques[$bk](); }
    foreach ((array) ($r['hamb'] ?? []) as $h) {
        $o = [];
        foreach ($ING[$h[1]] as $ing) { $o[] = ['label' => 'Sin ' . $ing, 'price' => '', 'image' => '', 'price_type' => 'flat_fee', 'visibility' => 1]; }
        $a[] = $nuevo($h[0], 'checkbox', ['description_enable' => 1, 'description' => 'Marca lo que NO quieres. [acordeon:Quitar ingredientes]', 'options' => $o]);
    }
    foreach ($a as $i => $x) { $a[$i]['position'] = $i; }
    $resumen = count($a) . ' extra(s): ' . implode(' | ', array_map(function ($x) { return $x['name'] . '[' . $x['type'] . ']'; }, array_slice($a, 0, 4))) . (count($a) > 4 ? ' …' : '');
    if ($dry) { echo "$pid {$post->post_title} [{$post->post_status}] → $resumen\n"; continue; }
    if (!get_post_meta($pid, '_product_addons_backup_promos', true)) {
        update_post_meta($pid, '_product_addons_backup_promos', get_post_meta($pid, '_product_addons', true) ?: []);
    }
    update_post_meta($pid, '_product_addons', $a);
    update_post_meta($pid, '_product_addons_exclude_global', '1');
    echo "$pid {$post->post_title} [{$post->post_status}] → $resumen\n";
}
