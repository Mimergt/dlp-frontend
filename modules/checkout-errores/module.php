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
    wc_get_logger()->warning('falla ' . wp_json_encode([
        'tipo'    => dlp_fe_ck_tipo(),
        'campos'  => $campos,
        'avisos'  => $otros,
        'movil'   => (bool) preg_match('/Mobi|Android|iPhone|iPad/i', $ua),
    ]), ['source' => 'dlp-checkout']);
});

add_action('woocommerce_checkout_order_processed', function () {
    $GLOBALS['dlp_fe_ck_ok'] = true;
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->info('ok ' . wp_json_encode(['tipo' => dlp_fe_ck_tipo()]), ['source' => 'dlp-checkout']);
    }
}, 99999);
