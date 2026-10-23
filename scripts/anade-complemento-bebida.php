<?php
/**
 * Agrega a las hamburguesas individuales dos extras OPCIONALES: «Añade un complemento» (productos de la categoría sides) y
 * «Añade una bebida» (productos de la categoría bebidas), con los nombres y precios actuales del catálogo.
 * Idempotente: si el producto ya tiene el extra lo actualiza. Solo dev. Uso: wp eval-file anade-complemento-bebida.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);
$IND = [2131, 159000, 159002, 2139, 159004, 2137, 2135, 124437, 124441, 2141, 2143, 101946, 101942, 101950, 101960];

function dlp_opciones_cat($slug) {
    $ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 50, 'orderby' => 'menu_order', 'order' => 'ASC',
        'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug]]]);
    $o = [];
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if (!$p || !$p->is_visible()) { continue; }
        $precio = (float) $p->get_price();
        $o[] = ['label' => html_entity_decode($p->get_name()), 'price' => $precio ? (string) $precio : '', 'image' => '', 'price_type' => 'quantity_based', 'visibility' => 1];
    }
    return $o;
}
$comp = dlp_opciones_cat('sides');
$beb  = dlp_opciones_cat('bebidas');
echo 'Complementos: ' . implode(' | ', array_map(function ($x) { return $x['label'] . ' Q' . $x['price']; }, $comp)) . "\n";
echo 'Bebidas: ' . implode(' | ', array_map(function ($x) { return $x['label'] . ' Q' . $x['price']; }, $beb)) . "\n";

$base = (int) (microtime(true) * 10) % 1000000000; $k = 0;
$campo = function ($nombre, $opts) use (&$k, $base) {
    return ['name' => $nombre, 'title_format' => 'heading', 'description_enable' => 0, 'description' => '', 'placeholder_enable' => 0, 'placeholder' => '',
        'type' => 'multiple_choice', 'display' => 'select', 'position' => 0, 'required' => 0, 'restrictions' => 0, 'restrictions_type' => 'any_text',
        'adjust_price' => 0, 'price_type' => 'flat_fee', 'price' => '', 'id' => (string) ($base + $k++), 'min' => 0, 'max' => 0, 'default' => '', 'options' => $opts];
};
foreach ($IND as $pid) {
    $post = get_post($pid);
    if (!$post) { echo "$pid: no existe\n"; continue; }
    $a = get_post_meta($pid, '_product_addons', true);
    $a = is_array($a) ? $a : [];
    $a = array_values(array_filter($a, function ($x) { return !in_array($x['name'] ?? '', ['Añade un complemento', 'Añade una bebida'], true); }));
    $a[] = $campo('Añade un complemento', $comp);
    $a[] = $campo('Añade una bebida', $beb);
    foreach ($a as $i => $x) { $a[$i]['position'] = $i; }
    if ($dry) { echo "$pid {$post->post_title}: quedaría con " . count($a) . " extra(s)\n"; continue; }
    if (!get_post_meta($pid, '_product_addons_backup_anade', true)) { update_post_meta($pid, '_product_addons_backup_anade', get_post_meta($pid, '_product_addons', true) ?: []); }
    update_post_meta($pid, '_product_addons', $a);
    update_post_meta($pid, '_product_addons_exclude_global', '1');
    echo "$pid " . html_entity_decode($post->post_title) . ': ' . count($a) . " extra(s) (" . implode(' | ', array_map(function ($x) { return $x['name']; }, $a)) . ")\n";
}
