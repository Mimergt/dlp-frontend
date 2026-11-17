<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout: errores claros y registro de fallas.
 * 1) Migración única: «Escribe tu dirección completa» y «Referencia de tu dirección» eran obligatorios en la configuración
 *    guardada del editor de campos (dlp_fe_checkout_fields), aunque los valores por defecto son opcionales. Rechazaban la mayoría
 *    de los pedidos que dejaban esos campos vacíos y solo habían marcado la ubicación en el mapa.
 * 2) Mensaje de campo requerido sin el prefijo «Facturación».
 * 3) Registro (sin datos personales) de cada validación fallida y de cada pedido creado: WooCommerce → Estado → Registros, source dlp-checkout.
 */

add_action('init', function () {
    if (get_option('dlp_fe_cf_opcional_v1')) {
        return;
    }
    if (defined('DLP_FE_CF_OPTION')) {
        $s = get_option(DLP_FE_CF_OPTION);
        if (is_array($s) && $s) {
            $cambio = false;
            foreach ($s as &$it) {
                if (isset($it['key']) && in_array($it['key'], ['billing_address_2', 'billing_address_name'], true) && !empty($it['required'])) {
                    $it['required'] = 0;
                    $cambio = true;
                }
            }
            unset($it);
            if ($cambio) {
                update_option(DLP_FE_CF_OPTION, $s, false);
            }
        }
    }
    update_option('dlp_fe_cf_opcional_v1', time(), false);
}, 30);

/*
 * Envío de WooCommerce apagado: el delivery lo resuelve Del Puente Tiendas (cobertura por mapa, sin tarifas). Con un método de
 * envío activo en una zona de WooCommerce (producción tenía «Envío gratuito» en la zona GT) WooCommerce exige calcular un envío
 * y rechazaba el pedido con «Por favor introduce una dirección para continuar» (error 'shipping') cuando el país o la zona del
 * destino no coincidían; en dev, sin métodos activos, WooCommerce no pide envío y nunca falló. Filtro dlp_fe_ck_sin_envio_wc.
 */
add_filter('woocommerce_cart_needs_shipping', function ($needs) {
    return apply_filters('dlp_fe_ck_sin_envio_wc', true) ? false : $needs;
}, 99999);
add_filter('woocommerce_cart_needs_shipping_address', function ($needs) {
    return apply_filters('dlp_fe_ck_sin_envio_wc', true) ? false : $needs;
}, 99999);

// «Escribe tu dirección completa es un campo requerido.» (sin «Facturación » delante)
add_filter('woocommerce_checkout_required_field_notice', function ($notice, $label = '') {
    $label = preg_replace('/^(Facturación|Envío|Billing|Shipping)\s+/u', '', wp_strip_all_tags((string) $label));
    return '<strong>' . esc_html($label) . '</strong> es un campo requerido.';
}, 10, 2);

/* ---- Registro de fallas (sin nombre, teléfono, correo ni dirección) ---- */
function dlp_fe_ck_en_checkout() {
    return defined('WOOCOMMERCE_CHECKOUT') && WOOCOMMERCE_CHECKOUT && !empty($_POST['woocommerce-process-checkout-nonce']); // phpcs:ignore WordPress.Security.NonceVerification
}

function dlp_fe_ck_tipo() {
    $t = isset($_POST['woofood_order_type']) ? sanitize_key(wp_unslash($_POST['woofood_order_type'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    return $t === 'pickup' ? 'pickup' : 'delivery';
}

/** Categoría genérica de un aviso, para no guardar el texto (puede traer datos). */
function dlp_fe_ck_categoria($m) {
    $t = mb_strtolower(wp_strip_all_tags((string) $m));
    $map = [
        'direcci.n para continuar|m.todo de env|shipping|no enviamos|no hacemos env' => 'envio_wc',
        'ubicaci|mapa|cobertura|llegamos' => 'ubicacion',
        'minimo|mínimo' => 'minimo',
        'hora|recoger|recogeras' => 'pickup_hora_tienda',
        'cuenta|registrad' => 'cuenta',
        'bloquead|antifraude|rechaz' => 'bloqueo',
        'pago|tarjeta|cybersource|payer' => 'pago',
        'sitio cerrado|horario|cerrad|no recibimos' => 'horario',
        'requerido' => 'campo_requerido',
    ];
    foreach ($map as $re => $cat) {
        if (preg_match('/' . $re . '/u', $t)) {
            return $cat;
        }
    }
    return 'otro';
}

$GLOBALS['dlp_fe_ck_fallas'] = ['campos' => [], 'otros' => []];

add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if ($errors instanceof WP_Error) {
        foreach ($errors->get_error_codes() as $c) {
            $GLOBALS['dlp_fe_ck_fallas']['campos'][] = sanitize_key($c);
        }
    }
}, 99999, 2);

add_filter('woocommerce_add_error', function ($m) {
    if (dlp_fe_ck_en_checkout()) {
        $GLOBALS['dlp_fe_ck_fallas']['otros'][] = dlp_fe_ck_categoria($m);
    }
    return $m;
}, 99999);

add_action('shutdown', function () {
    if (!dlp_fe_ck_en_checkout() || !function_exists('wc_get_logger') || !empty($GLOBALS['dlp_fe_ck_ok'])) {
        return;
    }
    $f = $GLOBALS['dlp_fe_ck_fallas'];
    $campos = array_values(array_unique($f['campos']));
    $otros  = array_values(array_unique($f['otros']));
    if (!$campos && !$otros) {
        return;
    }
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
    $log = [
        'tipo'    => dlp_fe_ck_tipo(),
        'campos'  => $campos,
        'avisos'  => $otros,
        'movil'   => (bool) preg_match('/Mobi|Android|iPhone|iPad/i', $ua),
    ];
    // Si la falla es de envío (código 'shipping'): contexto técnico sin datos personales (país/departamento son códigos, no direcciones).
    if (in_array('shipping', $campos, true) && function_exists('WC') && WC()->cart && WC()->customer) {
        $pk = WC()->shipping() ? WC()->shipping()->get_packages() : [];
        $log['envio'] = [
            'necesita_envio'  => (bool) WC()->cart->needs_shipping(),
            'pais_envio'      => (string) WC()->customer->get_shipping_country(),
            'pais_factura'    => (string) WC()->customer->get_billing_country(),
            'estado_factura'  => (string) WC()->customer->get_billing_state(),
            'paquetes'        => count($pk),
            'tarifas'         => array_sum(array_map(function ($p) { return count((array) ($p['rates'] ?? [])); }, $pk)),
            'metodos_activos' => function_exists('wc_get_shipping_method_count') ? (int) wc_get_shipping_method_count(true) : null,
        ];
    }
    wc_get_logger()->warning('falla ' . wp_json_encode($log), ['source' => 'dlp-checkout']);
});

add_action('woocommerce_checkout_order_processed', function () {
    $GLOBALS['dlp_fe_ck_ok'] = true;
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info('ok ' . wp_json_encode(['tipo' => dlp_fe_ck_tipo()]), ['source' => 'dlp-checkout']);
    }
}, 99999);
