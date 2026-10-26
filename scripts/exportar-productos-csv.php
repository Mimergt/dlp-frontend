<?php
/** Exporta productos (descripción, opciones y extras) a CSV para revisión con el cliente. Solo lectura. Uso: wp eval-file exportar-productos-csv.php > productos.csv */
$out = fopen('php://output', 'w');
fputcsv($out, ['Producto', 'Categoría', 'Precio base (Q)', 'Estado', 'Descripción', 'Grupo de opciones', 'Tipo de selección', 'Opción', 'Extra (Q)', 'Incluida por defecto', 'ID']);
$limpiar = function ($t) {
    $t = preg_replace('/\[(grupo|acordeon|elegir):[^\]]*\]/iu', '', (string) $t);
    $t = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags(html_entity_decode($t))));
    return $t;
};
$tipos = ['multiple_choice' => 'Elegir una', 'checkbox' => 'Marcar varias', 'input_multiplier' => 'Cantidades', 'custom_text' => 'Texto', 'custom_textarea' => 'Texto'];
$q = new WP_Query(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private'], 'posts_per_page' => -1, 'orderby' => 'menu_order title', 'order' => 'ASC']);
foreach ($q->posts as $p) {
    $cats = wp_get_post_terms($p->ID, 'product_cat', ['fields' => 'names']);
    $prod = wc_get_product($p->ID);
    $base = [html_entity_decode($p->post_title), html_entity_decode(implode(', ', $cats)), $prod ? $prod->get_price() : '', $p->post_status === 'publish' ? 'Publicado' : 'Borrador', $limpiar($p->post_content ?: $p->post_excerpt)];
    $adds = get_post_meta($p->ID, '_product_addons', true);
    $first = true;
    $pri = function () use (&$first, $base) { if ($first) { $first = false; return $base; } return ['', '', '', '', '']; };
    if (!is_array($adds) || !$adds) { fputcsv($out, array_merge($pri(), ['', '', '', '', '', $p->ID])); continue; }
    foreach ($adds as $a) {
        $g = $limpiar($a['name'] ?? '');
        $t = $tipos[$a['type'] ?? ''] ?? ($a['type'] ?? '');
        $opts = (array) ($a['options'] ?? []);
        $def = $a['default'] ?? '';
        if ($a['type'] === 'input_multiplier' && !$opts) { $opts = [['label' => $g, 'price' => $a['price'] ?? '']]; }
        if (!$opts) { fputcsv($out, array_merge($pri(), [$g, $t, '', '', '', $p->ID])); continue; }
        foreach ($opts as $i => $o) {
            $pr = ($o['price'] ?? '') !== '' ? $o['price'] : (($a['type'] ?? '') === 'input_multiplier' ? ($a['price'] ?? '') : '');
            fputcsv($out, array_merge($pri(), [$g, $t, $limpiar($o['label'] ?? ''), $pr, ($def !== '' && (string) $def === (string) $i) ? 'Sí' : '', $p->ID]));
        }
    }
}
