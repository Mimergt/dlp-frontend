<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Traducciones exactas (texto original en inglés => español) de plugins sin traducción.
 * Se aplica en gettext a los dominios de la pasarela de tarjeta (Cybersource + SkyVerge).
 * Las claves no distinguen mayúsculas ni el punto final.
 */
function dlp_fe_tr_mapa() {
    static $m = null;
    if ($m !== null) {
        return $m;
    }
    $raw = [
        // Formulario de tarjeta
        'Card Number' => 'Número de tarjeta',
        'Expiration (MM/YY)' => 'Vencimiento (MM/AA)',
        'MM / YY' => 'MM / AA',
        'Card Security Code' => 'Código de seguridad',
        'Card Security Code (CSC)' => 'Código de seguridad (CVV)',
        'Saved Card Security Code' => 'Código de seguridad de la tarjeta guardada',
        'CSC' => 'CVV',
        'Securely Save to Account' => 'Guardar mi tarjeta de forma segura',
        'Where do I find this?' => '¿Dónde lo encuentro?',
        'Use a new card' => 'Usar una tarjeta nueva',
        'Payment Info' => 'Datos de pago',
        'Credit Card' => 'Tarjeta de crédito',
        'Place order' => 'Realizar el pedido',
        'Continue to Payment' => 'Continuar al pago',
        // Validación del formulario
        'Card expiration date is invalid' => 'La fecha de vencimiento de la tarjeta no es válida',
        'Card number is missing' => 'Falta el número de tarjeta',
        'Card number is invalid' => 'El número de tarjeta no es válido',
        'Card number is invalid (wrong length)' => 'El número de tarjeta no es válido (longitud incorrecta)',
        'Card number is invalid (only digits allowed)' => 'El número de tarjeta no es válido (solo dígitos)',
        'Card is invalid' => 'La tarjeta no es válida',
        '{card_type} card is invalid' => 'La tarjeta {card_type} no es válida',
        'Card security code is missing' => 'Falta el código de seguridad',
        'Card security code is invalid (must be 3 or 4 digits)' => 'El código de seguridad no es válido (debe tener 3 o 4 dígitos)',
        'Card security code is invalid (only digits are allowed)' => 'El código de seguridad no es válido (solo dígitos)',
        // Respuestas del pago
        'Payment error, please try another payment method or contact us to complete your transaction' => 'Error en el pago. Intenta con otro método de pago o comunícate con nosotros para completar tu pedido.',
        'An error occurred, please try again or try an alternate form of payment' => 'Ocurrió un error. Intenta de nuevo o usa otra forma de pago.',
        'The transaction failed' => 'La transacción falló.',
        'Unknown error' => 'Error desconocido',
        'Payer authentication failed' => 'No se pudo verificar la tarjeta. Intenta de nuevo o usa otra forma de pago.',
        'Payer Authentication is required for the selected payment method. Please try again or contact the store for further information' => 'Este método de pago requiere verificación adicional. Intenta de nuevo o comunícate con la tienda.',
        "We're sorry, this order could not be processed due to fraud risks" => 'Lo sentimos, no pudimos procesar este pedido por motivos de seguridad.',
        'Please verify your billing information and try again' => 'Verifica tus datos de facturación e intenta de nuevo.',
        'The provided card is expired, please use an alternate card or other form of payment' => 'La tarjeta está vencida. Usa otra tarjeta u otra forma de pago.',
        'The provided card was declined, please use an alternate card or other form of payment' => 'La tarjeta fue rechazada. Usa otra tarjeta u otra forma de pago.',
        'Insufficient funds in account, please use an alternate card or other form of payment' => 'Fondos insuficientes. Usa otra tarjeta u otra forma de pago.',
        'The card is inactivate or not authorized for card-not-present transactions, please use an alternate card or other form of payment' => 'La tarjeta está inactiva o no está autorizada para compras en línea. Usa otra tarjeta u otra forma de pago.',
        'The credit limit for the card has been reached, please use an alternate card or other form of payment' => 'La tarjeta alcanzó su límite de crédito. Usa otra tarjeta u otra forma de pago.',
        'The card verification number is invalid, please try again' => 'El código de seguridad no es válido. Intenta de nuevo.',
        'The provided card number was invalid, or card type was incorrect.  Please try again' => 'El número de tarjeta no es válido o el tipo de tarjeta es incorrecto. Intenta de nuevo.',
        'That card type is not accepted, please use an alternate card or other form of payment' => 'Ese tipo de tarjeta no se acepta. Usa otra tarjeta u otra forma de pago.',
        'The card type is invalid or does not correlate with the credit card number.  Please try again or use an alternate card or other form of payment' => 'El tipo de tarjeta no coincide con el número. Intenta de nuevo o usa otra forma de pago.',
        'We cannot process your order with the payment information that you provided.  Please use a different payment account or an alternate payment method' => 'No podemos procesar tu pedido con los datos de pago ingresados. Usa otra tarjeta u otro método de pago.',
        'This order is being placed on hold for review due to an incorrect card verification number. You may contact the store to complete the transaction' => 'Tu pedido quedó en revisión porque el código de seguridad es incorrecto. Comunícate con la tienda para completar la compra.',
        'This order is being placed on hold for review. You may contact the store to complete the transaction' => 'Tu pedido quedó en revisión. Comunícate con la tienda para completar la compra.',
        'This order is being placed on hold for review due to a communication error. You may contact the store to complete the transaction' => 'Tu pedido quedó en revisión por un error de comunicación. Comunícate con la tienda para completar la compra.',
        'Your order has been received and is being reviewed. Thank you for your business' => 'Recibimos tu pedido y lo estamos revisando. ¡Gracias por tu compra!',
        'Thank you for your order' => '¡Gracias por tu pedido!',
        'Connection error: "%s"' => 'Error de conexión: "%s"',
        '%1$sCurrently unavailable. Please try a different payment method.%2$s' => '%1$sNo disponible por el momento. Elige otro método de pago.%2$s',
        // Mi cuenta → Tarjetas
        'Nice! New payment method added.' => '¡Listo! Se agregó el método de pago.',
        'Nice! New payment method added: %1$s ending in %2$s (expires %3$s)' => '¡Listo! Se agregó %1$s terminada en %2$s (vence %3$s)',
        'Nice! New payment method added: %1$s account ending in %2$s' => '¡Listo! Se agregó la cuenta %1$s terminada en %2$s',
        'Oops, adding your new payment method failed: %s' => 'No se pudo agregar tu método de pago: %s',
        '%1$s ending in %2$s' => '%1$s terminada en %2$s',
        '(expires %s)' => '(vence %s)',
        'Default' => 'Predeterminada',
        'Use a new bank account' => 'Usar una cuenta bancaria nueva',
        'Manage Payment Methods' => 'Administrar métodos de pago',
    ];
    $m = [];
    foreach ($raw as $k => $v) {
        $m[dlp_fe_tr_key($k)] = $v;
    }
    return $m;
}

function dlp_fe_tr_key($s) {
    return strtolower(rtrim(trim($s), '. '));
}

function dlp_fe_tr_aplicar($translated, $text, $domain) {
    if ($domain !== 'woocommerce-plugin-framework' && $domain !== 'woocommerce-gateway-cybersource') {
        return $translated;
    }
    $m = dlp_fe_tr_mapa();
    $k = dlp_fe_tr_key($text);
    if (!isset($m[$k])) {
        return $translated;
    }
    // Mantiene el punto final si el original lo traía y la traducción no.
    return $m[$k];
}

add_filter('gettext', 'dlp_fe_tr_aplicar', 20, 3);
add_filter('gettext_with_context', function ($translated, $text, $context, $domain) {
    return dlp_fe_tr_aplicar($translated, $text, $domain);
}, 20, 4);
