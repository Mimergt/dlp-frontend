<?php
/**
 * Divi: saca los estilos de los módulos del PIE y los pone en la cabecera (opción `et_pb_css_in_footer` = off).
 * Con «on», el CSS del encabezado (botones Menú/Restaurantes/Mi cuenta) llegaba al final del HTML y el contenido
 * saltaba de lugar al cargar (CLS ≈ 0.3). Con «off» va en el CSS crítico de la cabecera: CLS 0.
 * Uso:  wp eval-file scripts/divi-css-en-head.php [aplicar|revertir]   (sin argumento: solo muestra el valor actual)
 * Antes de aplicar, respalda la opción `et_divi` (wp option get et_divi --format=json > respaldo.json).
 */
$modo = $args[0] ?? '';
$o    = get_option('et_divi', []);
echo 'et_pb_css_in_footer actual: ' . ($o['et_pb_css_in_footer'] ?? '(sin valor)') . "\n";
if ($modo === 'aplicar' || $modo === 'revertir') {
    $o['et_pb_css_in_footer'] = $modo === 'aplicar' ? 'off' : 'on';
    update_option('et_divi', $o);
    if (class_exists('ET_Core_PageResource')) {
        ET_Core_PageResource::remove_static_resources('all', 'all');
    }
    echo 'nuevo valor: ' . $o['et_pb_css_in_footer'] . " (caché de Divi limpiada)\n";
}
