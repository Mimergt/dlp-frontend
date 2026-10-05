<?php
if (!defined('ABSPATH')) {
    exit;
}

// Campos del checkout.
add_filter('woocommerce_checkout_fields', function ($fields) {
    if (is_user_logged_in()) {
        unset($fields['billing']['user_bday'], $fields['shipping']['shipping_first_name']);
    }
    unset($fields['billing']['billing_postcode']);
    return $fields;
});

add_filter('woocommerce_billing_fields', function ($fields) {
    unset($fields['billing_postcode']);
    return $fields;
});

// Dirección no obligatoria.
add_filter('woocommerce_default_address_fields', function ($fields) {
    $fields['address_1']['required'] = false;
    return $fields;
}, 99999);

/*
 * Cuenta de invitado por correo.
 * - Si el correo no existe se crea la cuenta; si existe no.
 * - El pedido de un invitado con correo registrado se asocia a esa cuenta.
 * SEGURIDAD: el tema original dejaba que WooCommerce además SOBRESCRIBIERA los datos guardados de la cuenta
 * (nombre, teléfono, dirección) con lo que escribiera cualquier invitado con ese correo. Ahora esos datos
 * solo se actualizan si la cuenta es nueva o si la persona inició sesión.
 */
$GLOBALS['dlp_fe_guest_attached'] = false;

add_filter('woocommerce_checkout_posted_data', function ($data) {
    if (!empty($data['billing_email'])) {
        $data['createaccount'] = get_user_by('email', $data['billing_email']) ? 0 : 1;
    }
    return $data;
});

add_filter('woocommerce_checkout_customer_id', function ($user_id) {
    if (!$user_id && !empty($_POST['billing_email'])) {
        $user = get_user_by('email', sanitize_email(wp_unslash($_POST['billing_email'])));
        if ($user) {
            $GLOBALS['dlp_fe_guest_attached'] = true;
            return $user->ID;
        }
    }
    return $user_id;
});

add_filter('woocommerce_checkout_update_customer_data', function ($update) {
    return !empty($GLOBALS['dlp_fe_guest_attached']) ? false : $update;
});

add_action('woocommerce_new_order', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_user_id()) {
        return;
    }
    $user = get_user_by('email', $order->get_billing_email());
    if ($user) {
        $order->set_customer_id($user->ID);
        $order->save();
    }
});
