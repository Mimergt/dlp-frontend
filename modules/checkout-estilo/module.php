<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Estilo de los campos de tarjeta de Cybersource (Flex Microform): son iframes del banco y el CSS de la página no entra,
 * así que el tamaño y color del texto se pasan en los argumentos del formulario. Letra de 16 px (iOS no hace zoom),
 * color de la marca y marcador gris; las cajas que los rodean se dibujan en style.css (siempre claras, por el color del texto).
 */
add_filter('wc_cybersource_credit_card_payment_form_js_args', function ($args) {
    $args['styles'] = [
        'input'         => ['font-size' => '16px', 'font-weight' => '400', 'color' => '#111111', 'font-family' => 'system-ui, -apple-system, "Segoe UI", Arial, sans-serif', 'letter-spacing' => '.02em', 'line-height' => '44px'],
        '::placeholder' => ['color' => '#a3a3ad'],
        'invalid'       => ['color' => '#b3261e'],
    ];
    return $args;
});
