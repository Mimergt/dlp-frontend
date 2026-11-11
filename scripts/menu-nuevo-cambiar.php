<?php
/**
 * Pone el menú nuevo en /menu/ o vuelve al viejo (wp eval-file scripts/menu-nuevo-cambiar.php aplicar|revertir|estado).
 *  aplicar : la página vieja (slug «menu») pasa a «menu-antiguo» en borrador; la nueva («menu-nuevo») toma el slug «menu»
 *            y el ajuste `slug` del módulo menu-nuevo pasa a «menu». WordPress redirige solo /menu-nuevo/ → /menu/.
 *  revertir: deja todo como estaba antes (usa lo guardado en la opción dlp_fe_mn_cambio).
 * Luego purgar /menu/ y /menu-nuevo/ en Cloudflare. Los IDs de las páginas son de dev: en producción se buscan por slug.
 */
$accion = isset($args[0]) ? $args[0] : 'estado';
$G = 'dlp_fe_mn_cambio';
$mods = get_option('dlp_fe_modules', []);
$slug_actual = isset($mods['menu-nuevo']['settings']['slug']) ? $mods['menu-nuevo']['settings']['slug'] : '(por defecto: menu-nuevo)';
$vieja = get_page_by_path('menu'); $nueva = get_page_by_path('menu-nuevo'); $ant = get_page_by_path('menu-antiguo');
if ($accion === 'estado') {
    echo 'menu: ' . ($vieja ? $vieja->ID . ' (' . $vieja->post_status . ')' : '—') . ' | menu-nuevo: ' . ($nueva ? $nueva->ID : '—') . ' | menu-antiguo: ' . ($ant ? $ant->ID . ' (' . $ant->post_status . ')' : '—') . ' | ajuste slug: ' . $slug_actual . "\n";
    echo 'guardado: ' . wp_json_encode(get_option($G)) . "\n";
    return;
}
if ($accion === 'aplicar') {
    if (!$vieja || !$nueva) { echo "Faltan páginas: hace falta una con slug «menu» y otra «menu-nuevo».\n"; return; }
    if ($ant) { echo "Ya existe «menu-antiguo» (ID {$ant->ID}): ya se aplicó o hay que borrarla antes.\n"; return; }
    update_option($G, ['vieja' => $vieja->ID, 'vieja_estado' => $vieja->post_status, 'nueva' => $nueva->ID, 'ajuste_antes' => isset($mods['menu-nuevo']['settings']['slug']) ? $mods['menu-nuevo']['settings']['slug'] : null], false);
    wp_update_post(['ID' => $vieja->ID, 'post_name' => 'menu-antiguo', 'post_status' => 'draft']);
    wp_update_post(['ID' => $nueva->ID, 'post_name' => 'menu', 'post_title' => 'Menú']);
    $mods['menu-nuevo']['settings']['slug'] = 'menu';
    update_option('dlp_fe_modules', $mods);
    update_option('dlp_fe_mn_ver', (string) microtime(true), false);
    echo "Aplicado: página {$nueva->ID} ahora es /menu/ y la vieja ({$vieja->ID}) quedó en borrador como menu-antiguo.\n";
    return;
}
if ($accion === 'revertir') {
    $g = get_option($G);
    if (!is_array($g)) { echo "No hay nada guardado que revertir.\n"; return; }
    wp_update_post(['ID' => $g['nueva'], 'post_name' => 'menu-nuevo', 'post_title' => 'Menú (nuevo)']);
    wp_update_post(['ID' => $g['vieja'], 'post_name' => 'menu', 'post_status' => $g['vieja_estado']]);
    if ($g['ajuste_antes'] === null) { unset($mods['menu-nuevo']['settings']['slug']); } else { $mods['menu-nuevo']['settings']['slug'] = $g['ajuste_antes']; }
    update_option('dlp_fe_modules', $mods);
    update_option('dlp_fe_mn_ver', (string) microtime(true), false);
    delete_option($G);
    echo "Revertido: la página {$g['vieja']} vuelve a ser /menu/ y la {$g['nueva']} a /menu-nuevo/.\n";
    return;
}
echo "Uso: aplicar | revertir | estado\n";
