<?php
/**
 * Pone el menú nuevo en la página /menu/ o vuelve al viejo (wp eval-file scripts/menu-nuevo-cambiar.php aplicar|revertir|estado).
 * La página /menu/ NO se reemplaza ni cambia de URL, de ID ni de SEO (título y descripción de Yoast, imagen destacada…):
 * solo cambia su contenido.
 *  aplicar : 1) guarda una copia de la página (contenido y todas sus metas) como borrador «Menú (antiguo)» (slug menu-antiguo);
 *            2) sustituye el contenido de /menu/ por un marcador (el módulo menu-nuevo pinta el menú encima);
 *            3) pone el ajuste `slug` del módulo menu-nuevo en «menu».
 *  revertir: devuelve a /menu/ el contenido guardado, pone el ajuste como estaba y manda la copia a la papelera.
 * Después purgar /menu/ en Cloudflare. No usa IDs fijos: busca la página por slug, así sirve igual en producción.
 */
$accion = isset($args[0]) ? $args[0] : 'estado';
$G = 'dlp_fe_mn_cambio';
$MARCA = '<!-- menu nuevo: lo pinta el módulo menu-nuevo de dlp-frontend; el contenido anterior está en la página «Menú (antiguo)» (borrador) -->';
global $wpdb;
$mods = get_option('dlp_fe_modules', []);
$viva = get_page_by_path('menu');
$ant  = get_page_by_path('menu-antiguo', OBJECT, 'page');
$ajuste = isset($mods['menu-nuevo']['settings']['slug']) ? $mods['menu-nuevo']['settings']['slug'] : null;

if ($accion === 'estado') {
    echo 'menu: ' . ($viva ? $viva->ID . ' (' . $viva->post_status . ', ' . strlen($viva->post_content) . ' caracteres de contenido)' : '—') . ' | menu-antiguo: ' . ($ant ? $ant->ID . ' (' . $ant->post_status . ')' : '—') . ' | ajuste slug: ' . ($ajuste ?: '(por defecto menu-nuevo)') . "\n";
    echo 'guardado: ' . wp_json_encode(get_option($G)) . "\n";
    return;
}
if ($accion === 'aplicar') {
    if (!$viva) { echo "No hay una página con slug «menu».\n"; return; }
    if ($ant || get_option($G)) { echo "Ya se aplicó (existe «menu-antiguo» o la opción $G). Revertir primero si hace falta.\n"; return; }
    $clon = wp_insert_post([
        'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Menú (antiguo)', 'post_name' => 'menu-antiguo',
        'post_content' => $viva->post_content, 'post_excerpt' => $viva->post_excerpt, 'post_author' => $viva->post_author, 'menu_order' => $viva->menu_order,
    ], true);
    if (is_wp_error($clon)) { echo 'No se pudo crear la copia: ' . $clon->get_error_message() . "\n"; return; }
    $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) SELECT %d, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key NOT IN ('_edit_lock', '_edit_last', '_wp_old_slug')", $clon, $viva->ID));
    wp_update_post(['ID' => $viva->ID, 'post_content' => $MARCA]);
    update_option($G, ['viva' => $viva->ID, 'clon' => $clon, 'ajuste_antes' => $ajuste], false);
    $mods['menu-nuevo']['settings']['slug'] = 'menu';
    update_option('dlp_fe_modules', $mods);
    update_option('dlp_fe_mn_ver', (string) microtime(true), false);
    echo "Aplicado: /menu/ (ID {$viva->ID}) conserva su URL, SEO e imagen y ahora muestra el menú nuevo; el contenido anterior quedó en el borrador «Menú (antiguo)» (ID $clon).\n";
    return;
}
if ($accion === 'revertir') {
    $g = get_option($G);
    if (!is_array($g) || empty($g['clon']) || !($c = get_post($g['clon']))) { echo "No hay copia guardada que restaurar.\n"; return; }
    wp_update_post(['ID' => $g['viva'], 'post_content' => $c->post_content]);
    if ($g['ajuste_antes'] === null) { unset($mods['menu-nuevo']['settings']['slug']); } else { $mods['menu-nuevo']['settings']['slug'] = $g['ajuste_antes']; }
    update_option('dlp_fe_modules', $mods);
    update_option('dlp_fe_mn_ver', (string) microtime(true), false);
    wp_trash_post($g['clon']);
    delete_option($G);
    echo "Revertido: /menu/ (ID {$g['viva']}) tiene otra vez su contenido anterior; la copia ({$g['clon']}) está en la papelera.\n";
    return;
}
echo "Uso: aplicar | revertir | estado\n";
