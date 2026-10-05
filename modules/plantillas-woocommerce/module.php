<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Aplica las plantillas de /templates si existen. Para sumar una: copiar la de WooCommerce a
 * templates/<misma ruta>, editarla, y comparar con la de WooCommerce cada vez que actualice.
 * Si el tema activo trae su propia versión, esa tiene prioridad.
 */
add_filter('woocommerce_locate_template', function ($template, $template_name) {
    $mia = __DIR__ . '/templates/' . $template_name;
    if (!is_readable($mia)) {
        return $template;
    }
    if (strpos($template, get_stylesheet_directory()) === 0 || strpos($template, get_template_directory()) === 0) {
        return $template;
    }
    return $mia;
}, 10, 2);
