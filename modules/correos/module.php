<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Correos de pedidos de Del Puente.
 *  1) Correo por pedido a la tienda asignada (extra_store_email), con copia oculta a pedidos@.
 *  2) Aviso a pedidos@ de pedido fallido o cancelado (reemplaza los correos de administrador de WooCommerce).
 *  3) Rediseño de los correos de WooCommerce al cliente (plantillas en /plantillas).
 *  4) Modo de pruebas: en dev/staging todo va al correo de pruebas.
 * No renombra ni toca metas woofood_*: solo las lee. Tienda del pedido = meta extra_store_name (ID del CPT extra_store).
 */

function dlp_fe_mail_cfg($key, $fallback = '') {
    $v = DLP_FE_Registry::setting('correos', $key, $fallback);
    return ($v === '' || $v === null) ? $fallback : $v;
}

/* ------------------------------------------------------------------
 * Seguridad: modo de pruebas
 * ------------------------------------------------------------------ */
function dlp_fe_mail_test_mode() {
    if ((int) dlp_fe_mail_cfg('modo_pruebas', 0) === 1) {
        return true;
    }
    if (defined('DLP_STAGING_GUARD') && DLP_STAGING_GUARD) {
        return true;
    }
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    return (bool) preg_match('/^(dev|staging|test|stg)\./i', $host);
}

function dlp_fe_mail_test_to() {
    $e = sanitize_email((string) dlp_fe_mail_cfg('correo_pruebas', 'm@epic.gt'));
    return $e ?: 'm@epic.gt';
}

/** En modo de pruebas ningún correo del sitio sale a un destinatario real: se redirige y se marca. */
add_filter('wp_mail', function ($args) {
    if (!dlp_fe_mail_test_mode()) {
        return $args;
    }
    $real = [];
    foreach ((array) ($args['to'] ?? []) as $t) {
        $real[] = trim((string) $t);
    }
    $headers = $args['headers'] ?? [];
    if (is_string($headers)) {
        $headers = preg_split("/\r\n|\n|\r/", $headers);
    }
    $keep = [];
    foreach ((array) $headers as $h) {
        if (preg_match('/^\s*(bcc|cc)\s*:\s*(.*)$/i', (string) $h, $m)) {
            $real[] = strtoupper($m[1]) . ': ' . trim($m[2]);
            continue;
        }
        $keep[] = $h;
    }
    $dest = implode(', ', array_filter($real));
    $args['to']      = dlp_fe_mail_test_to();
    $args['headers'] = $keep;
    $args['subject'] = '[PRUEBA] ' . preg_replace('/^\[PRUEBA\]\s*/', '', (string) ($args['subject'] ?? ''));
    $banner = '<div style="background:#fff3cd;color:#664d03;border:1px solid #ffe69c;padding:10px 14px;font:13px Arial,sans-serif;margin:0 0 12px">'
        . '<strong>Correo de prueba.</strong> Destinatario real: ' . esc_html($dest ?: '—') . '</div>';
    $msg = (string) ($args['message'] ?? '');
    if (stripos($msg, '<body') !== false) {
        $args['message'] = preg_replace('/(<body[^>]*>)/i', '$1' . $banner, $msg, 1);
    } else {
        $args['message'] = "[Destinatario real: " . $dest . "]\n\n" . $msg;
    }
    return $args;
}, 99);

/* ------------------------------------------------------------------
 * Los correos de administrador de WooCommerce se apagan: el único destino de administración es pedidos@
 * y recibe sus propios correos (copia de la tienda y aviso de fallido/cancelado).
 * ------------------------------------------------------------------ */
foreach (['new_order', 'cancelled_order', 'failed_order'] as $dlp_fe_mail_id) {
    add_filter('woocommerce_email_enabled_' . $dlp_fe_mail_id, '__return_false', 99);
}
unset($dlp_fe_mail_id);

/* ------------------------------------------------------------------
 * Plantillas propias de WooCommerce (cliente)
 * ------------------------------------------------------------------ */
add_filter('woocommerce_locate_template', function ($template, $name) {
    if (strpos($name, 'emails/') !== 0 || strpos($name, 'emails/plain/') === 0 || strpos($name, 'emails/block/') === 0) {
        return $template;
    }
    $file = __DIR__ . '/plantillas/' . substr($name, 7);
    return (substr($name, -4) === '.php' && file_exists($file)) ? $file : $template;
}, 20, 2);

/** Asuntos y títulos en español, solo cuando no se han personalizado en WooCommerce → Ajustes → Correos. */
add_action('init', function () {
    $textos = [
        'customer_processing_order' => ['Recibimos tu pedido #{order_number}', '¡Gracias por tu pedido!'],
        'customer_completed_order'  => ['Tu pedido #{order_number} fue entregado', '¡Buen provecho!'],
        'customer_failed_order'     => ['No pudimos procesar el pago de tu pedido #{order_number}', 'Tu pago no se completó'],
        'customer_on_hold_order'    => ['Tu pedido #{order_number} está en espera', 'Tu pedido está en espera'],
        'customer_refunded_order'   => ['Reembolso de tu pedido #{order_number}', 'Te reembolsamos tu pedido'],
        'customer_note'             => ['Una nota sobre tu pedido #{order_number}', 'Tienes una nota sobre tu pedido'],
        'customer_new_account'      => ['Bienvenido a Hamburguesas Del Puente', '¡Bienvenido a Del Puente!'],
        'customer_reset_password'   => ['Restablece tu contraseña de Hamburguesas Del Puente', 'Restablece tu contraseña'],
        'customer_verify_email'     => ['Confirma tu correo en Hamburguesas Del Puente', 'Confirma tu correo'],
    ];
    foreach ($textos as $id => $t) {
        // Los filtros de WooCommerce reciben ($texto, $objeto, $correo).
        add_filter('woocommerce_email_subject_' . $id, function ($subject, $obj = null, $email = null) use ($t) {
            // get_option() devuelve el texto por defecto si no se personalizó: solo se respeta lo que el cliente cambió a mano.
            if (is_object($email) && (string) $email->get_option('subject') !== (string) $email->get_default_subject()) {
                return $subject;
            }
            $n = (is_object($obj) && method_exists($obj, 'get_order_number')) ? $obj->get_order_number() : '';
            return str_replace('{order_number}', $n, $t[0]);
        }, 20, 3);
        add_filter('woocommerce_email_heading_' . $id, function ($heading, $obj = null, $email = null) use ($t) {
            if (is_object($email) && (string) $email->get_option('heading') !== (string) $email->get_default_heading()) {
                return $heading;
            }
            return $t[1];
        }, 20, 3);
    }
});

/* ------------------------------------------------------------------
 * Marca y diseño
 * ------------------------------------------------------------------ */
function dlp_fe_mail_color() {
    $c = sanitize_hex_color((string) dlp_fe_mail_cfg('color', '#111111'));
    return $c ?: '#111111';
}

function dlp_fe_mail_logo() {
    $l = (string) dlp_fe_mail_cfg('logo', '');
    if ($l === '') {
        $d = get_option('et_divi');
        $l = is_array($d) && !empty($d['divi_logo']) ? (string) $d['divi_logo'] : '';
    }
    return $l;
}

function dlp_fe_mail_icon_url($name) {
    return DLP_FE_URL . 'modules/correos/iconos/' . $name . '.gif?v=' . DLP_FE_VERSION;
}

/** Etiqueta de estado e ícono animado de cada correo de WooCommerce al cliente. */
function dlp_fe_mail_estado($email) {
    $id = is_object($email) && isset($email->id) ? (string) $email->id : '';
    $mapa = [
        'customer_processing_order' => ['EN PREPARACIÓN', 'proc'],
        'customer_completed_order'  => ['ENTREGADO', 'done'],
        'customer_failed_order'     => ['PAGO NO COMPLETADO', 'fail'],
        'customer_on_hold_order'    => ['EN ESPERA', 'hold'],
        'customer_refunded_order'   => ['REEMBOLSO', 'refund'],
        'customer_note'             => ['NOTA DEL PEDIDO', 'note'],
        'customer_new_account'      => ['CUENTA NUEVA', 'user'],
        'customer_reset_password'   => ['SEGURIDAD', 'key'],
        'customer_verify_email'     => ['CONFIRMA TU CORREO', 'mail'],
    ];
    if (!isset($mapa[$id])) {
        return ['', ''];
    }
    list($chip, $icon) = $mapa[$id];
    if ($icon === 'proc') { // motorista si es a domicilio, tienda si es para recoger
        $o = (is_object($email) && isset($email->object) && is_object($email->object) && method_exists($email->object, 'get_meta')) ? $email->object : null;
        $icon = ($o && dlp_fe_mail_is_pickup($o)) ? 'pickup' : 'delivery';
    }
    return [$chip, $icon];
}

/** Encabezado del correo (lo imprime plantillas/email-header.php): barra negra con logo y estado, ícono animado y título. */
function dlp_fe_mail_open($heading, $chip = '', $icon = '') {
    $c    = dlp_fe_mail_color();
    $logo = dlp_fe_mail_logo();
    $h  = '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . esc_html(wp_strip_all_tags((string) $heading)) . '</title></head>';
    $h .= '<body style="margin:0;padding:0;background:#ececec;-webkit-text-size-adjust:100%">';
    $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ececec"><tr><td align="center" style="padding:24px 12px">';
    $h .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;font-family:Arial,Helvetica,sans-serif;color:#2b2b2b">';
    $h .= '<tr><td style="background:' . $c . ';padding:14px 28px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td>';
    $h .= $logo !== '' ? '<img src="' . esc_url($logo) . '" alt="Hamburguesas Del Puente" height="30" style="display:block;border:0;height:30px;width:auto">'
        : '<span style="font-size:18px;font-weight:bold;color:#ffffff">Hamburguesas Del Puente</span>';
    $h .= '</td><td align="right" style="font-size:11px;letter-spacing:.14em;color:#bdbdbd;font-weight:bold">' . esc_html($chip) . '</td></tr></table></td></tr>';
    $h .= '<tr><td style="padding:28px 28px 4px"><table role="presentation" cellpadding="0" cellspacing="0"><tr>';
    if ($icon !== '') {
        $h .= '<td valign="middle" style="width:88px"><img src="' . esc_url(dlp_fe_mail_icon_url($icon)) . '" width="72" height="72" alt="" style="display:block;border:0;width:72px;height:72px"></td>';
    }
    $h .= '<td valign="middle"><h1 style="margin:0;font-size:25px;line-height:1.2;color:' . $c . '">' . esc_html(wp_strip_all_tags((string) $heading)) . '</h1></td></tr></table></td></tr>';
    $h .= '<tr><td style="padding:16px 28px 24px;font-size:15px;line-height:1.55;color:#2b2b2b">';
    return $h;
}

/** Pie del correo (lo imprime plantillas/email-footer.php). */
function dlp_fe_mail_close() {
    $c    = dlp_fe_mail_color();
    $pie  = trim((string) dlp_fe_mail_cfg('pie', 'Hamburguesas Del Puente · Guatemala'));
    $tel  = trim((string) dlp_fe_mail_cfg('contacto_telefono', ''));
    $mail = trim((string) dlp_fe_mail_cfg('contacto_correo', ''));
    $web  = trim((string) dlp_fe_mail_cfg('contacto_web', ''));
    $cont = [];
    if ($tel !== '') {
        $cont[] = 'Tel. <a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $tel)) . '" style="color:' . $c . ';text-decoration:none">' . esc_html($tel) . '</a>';
    }
    if ($mail !== '') {
        $cont[] = '<a href="mailto:' . esc_attr($mail) . '" style="color:' . $c . ';text-decoration:none">' . esc_html($mail) . '</a>';
    }
    if ($web !== '') {
        $cont[] = '<a href="' . esc_url($web) . '" style="color:' . $c . ';text-decoration:none">' . esc_html(preg_replace('#^https?://#', '', rtrim($web, '/'))) . '</a>';
    }
    $f  = '</td></tr>';
    $f .= '<tr><td style="background:#f6f6f6;border-top:1px solid #eee;padding:18px 28px;text-align:center;font-size:12px;line-height:1.6;color:#777">';
    if ($cont) {
        $f .= '<div style="margin-bottom:6px">' . implode(' &nbsp;·&nbsp; ', $cont) . '</div>';
    }
    $f .= '<div>' . nl2br(esc_html($pie)) . '</div>';
    $f .= '</td></tr></table></td></tr></table></body></html>';
    return $f;
}

/** Texto adicional de WooCommerce solo si se personalizó (el de fábrica, en inglés/noreply, no se muestra). */
function dlp_fe_mail_extra($email, $additional) {
    if (empty($additional) || !is_object($email)) {
        return '';
    }
    if ((string) $email->get_option('additional_content') === (string) $email->get_default_additional_content()) {
        return '';
    }
    return wp_kses_post(wpautop(wptexturize($additional)));
}

function dlp_fe_mail_p($html) {
    return '<p style="margin:0 0 14px">' . $html . '</p>';
}

function dlp_fe_mail_button($url, $label) {
    $c = dlp_fe_mail_color();
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px auto"><tr><td align="center" style="background:' . $c . ';border-radius:8px">'
        . '<a href="' . esc_url($url) . '" style="display:inline-block;padding:13px 28px;color:#ffffff;font-weight:bold;font-size:15px;text-decoration:none;font-family:Arial,Helvetica,sans-serif">' . esc_html($label) . '</a>'
        . '</td></tr></table>';
}

function dlp_fe_mail_box($html, $tono = 'gris') {
    return '<div style="background:#f3f3f3;border-left:4px solid ' . dlp_fe_mail_color() . ';border-radius:6px;padding:12px 16px;margin:0 0 16px">' . $html . '</div>';
}

/* ------------------------------------------------------------------
 * Datos del pedido
 * ------------------------------------------------------------------ */
function dlp_fe_mail_store_id($order) {
    return absint($order->get_meta('extra_store_name', true));
}

function dlp_fe_mail_store_info($store_id) {
    if (!$store_id || get_post_type($store_id) !== 'extra_store') {
        return ['name' => '', 'email' => '', 'phone' => '', 'address' => ''];
    }
    return [
        'name'    => get_the_title($store_id),
        'email'   => sanitize_email((string) get_post_meta($store_id, 'extra_store_email', true)),
        'phone'   => trim((string) get_post_meta($store_id, 'extra_store_phone', true)),
        'address' => trim((string) get_post_meta($store_id, 'extra_store_address', true)),
    ];
}

function dlp_fe_mail_is_pickup($order) {
    return $order->get_meta('woofood_order_type', true) === 'pickup';
}

function dlp_fe_mail_entrega_texto($order) {
    return dlp_fe_mail_is_pickup($order) ? 'Recoger en tienda' : 'Entrega a domicilio';
}

/** Hora acordada: pickup con hora programada o, si no, la hora del pedido. */
function dlp_fe_mail_hora($order) {
    $pt = $order->get_meta('woofood_time_to_deliver', true);
    if (is_numeric($pt) && (int) $pt > 1000000000) {
        return ['Hora programada', wp_date('g:i a', (int) $pt)];
    }
    $d = $order->get_date_created();
    return [dlp_fe_mail_is_pickup($order) ? 'Para recoger' : 'Entrega', 'Lo antes posible' . ($d ? ' (pedido a las ' . wp_date('g:i a', $d->getTimestamp()) . ')' : '')];
}

function dlp_fe_mail_direccion($order) {
    if (dlp_fe_mail_is_pickup($order)) {
        $s = dlp_fe_mail_store_info(dlp_fe_mail_store_id($order));
        return $s['address'];
    }
    $lines = array_filter([
        trim($order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2()),
        trim($order->get_shipping_city()),
    ]);
    if (!$lines) {
        $lines = array_filter([trim($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()), trim($order->get_billing_city())]);
    }
    $out = implode(', ', $lines);
    $et  = trim((string) $order->get_meta('billing_address_name', true));
    return $et !== '' ? $out . ' (' . $et . ')' : $out;
}

function dlp_fe_mail_row($label, $value_html) {
    return '<tr><td valign="top" style="padding:6px 12px 6px 0;color:#888;font-size:13px;white-space:nowrap">' . esc_html($label) . '</td>'
        . '<td valign="top" style="padding:6px 0;font-size:14px;color:#222">' . $value_html . '</td></tr>';
}

/** Productos con sus extras. */
function dlp_fe_mail_items_html($order) {
    $c = dlp_fe_mail_color();
    $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:4px 0 6px">';
    foreach ($order->get_items() as $item) {
        $extras = '';
        foreach ($item->get_formatted_meta_data('') as $m) {
            if ($m->key === 'woofood_meta' || strpos((string) $m->key, '_') === 0) {
                continue;
            }
            $val = trim(wp_strip_all_tags((string) $m->display_value));
            if ($val === '') {
                continue;
            }
            $extras .= '<div style="font-size:12px;color:#777;line-height:1.5">' . esc_html(wp_strip_all_tags((string) $m->display_key)) . ': ' . esc_html($val) . '</div>';
        }
        $h .= '<tr><td valign="top" style="padding:10px 0;border-bottom:1px solid #eee">'
            . '<div style="font-size:15px;color:#1d1d1d"><strong>' . esc_html($item->get_quantity()) . ' × ' . esc_html($item->get_name()) . '</strong></div>' . $extras . '</td>'
            . '<td valign="top" align="right" style="padding:10px 0 10px 12px;border-bottom:1px solid #eee;font-size:14px;color:#222;white-space:nowrap">' . wp_kses_post($order->get_formatted_line_subtotal($item)) . '</td></tr>';
    }
    $h .= '</table>';
    return $h;
}

function dlp_fe_mail_totals_html($order) {
    $c = dlp_fe_mail_color();
    $rows = [];
    foreach ($order->get_order_item_totals() as $key => $t) {
        $rows[] = [$key, rtrim((string) $t['label'], ':'), (string) $t['value']];
    }
    $h = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 6px">';
    foreach ($rows as $r) {
        if ($r[0] === 'payment_method') {
            continue;
        }
        $total = ($r[0] === 'order_total');
        $h .= '<tr><td style="padding:5px 0;font-size:' . ($total ? '16' : '14') . 'px;color:' . ($total ? '#1d1d1d' : '#666') . ';' . ($total ? 'font-weight:bold;border-top:2px solid ' . $c . ';padding-top:9px' : '') . '">' . esc_html(dlp_fe_mail_es($r[1])) . '</td>'
            . '<td align="right" style="padding:5px 0;font-size:' . ($total ? '16' : '14') . 'px;color:#1d1d1d;' . ($total ? 'font-weight:bold;border-top:2px solid ' . $c . ';padding-top:9px' : '') . '">' . wp_kses_post($r[2]) . '</td></tr>';
    }
    return $h . '</table>';
}

function dlp_fe_mail_es($s) {
    $map = ['Subtotal' => 'Subtotal', 'Shipping' => 'Envío', 'Total' => 'Total', 'Discount' => 'Descuento', 'Payment method' => 'Forma de pago', 'Note' => 'Nota', 'Tax' => 'Impuestos'];
    return $map[$s] ?? $s;
}

/**
 * Resumen del pedido. $o: cliente (mostrar datos del cliente), tienda (mostrar tienda), pago (forma de pago).
 */
function dlp_fe_mail_order_html($order, $o = []) {
    $o = $o + ['cliente' => false, 'tienda' => true, 'pago' => true];
    $c = dlp_fe_mail_color();
    $store = dlp_fe_mail_store_info(dlp_fe_mail_store_id($order));
    $hora  = dlp_fe_mail_hora($order);
    $fecha = $order->get_date_created();

    $h  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px"><tr>'
        . '<td style="font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.06em">Pedido</td>'
        . '<td align="right" style="font-size:12px;color:#888">' . ($fecha ? esc_html(wp_date('d/m/Y g:i a', $fecha->getTimestamp())) : '') . '</td></tr>'
        . '<tr><td colspan="2" style="font-size:26px;font-weight:bold;color:' . $c . ';padding-top:2px">#' . esc_html($order->get_order_number()) . '</td></tr></table>';

    $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border-top:1px solid #eee;border-bottom:1px solid #eee">';
    $h .= dlp_fe_mail_row('Tipo', '<strong>' . esc_html(dlp_fe_mail_entrega_texto($order)) . '</strong>');
    $h .= dlp_fe_mail_row($hora[0], esc_html($hora[1]));
    $dir = dlp_fe_mail_direccion($order);
    if ($dir !== '') {
        $h .= dlp_fe_mail_row(dlp_fe_mail_is_pickup($order) ? 'Dirección de la tienda' : 'Dirección', esc_html($dir));
    }
    if ($o['tienda'] && $store['name'] !== '') {
        $t = '<strong>' . esc_html($store['name']) . '</strong>';
        if ($store['phone'] !== '') {
            $t .= '<br><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $store['phone'])) . '" style="color:' . $c . ';text-decoration:none">' . esc_html($store['phone']) . '</a>';
        }
        $h .= dlp_fe_mail_row('Tienda', $t);
    }
    if ($o['cliente']) {
        $nom = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $tel = trim($order->get_billing_phone());
        $cl  = '<strong>' . esc_html($nom !== '' ? $nom : 'Cliente') . '</strong>';
        if ($tel !== '') {
            $cl .= '<br><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $tel)) . '" style="color:' . $c . ';text-decoration:none">' . esc_html($tel) . '</a>';
        }
        if ($order->get_billing_email() !== '') {
            $cl .= '<br>' . esc_html($order->get_billing_email());
        }
        $h .= dlp_fe_mail_row('Cliente', $cl);
    } else {
        $tel = trim($order->get_billing_phone());
        if ($tel !== '') {
            $h .= dlp_fe_mail_row('Teléfono', esc_html($tel));
        }
    }
    $nit = trim((string) $order->get_meta('billing_nit', true));
    if ($nit !== '') {
        $nn = trim((string) $order->get_meta('billing_nitname', true));
        $h .= dlp_fe_mail_row('Factura', esc_html($nit . ($nn !== '' ? ' · ' . $nn : '')));
    }
    if ($o['pago']) {
        $h .= dlp_fe_mail_row('Pago', esc_html($order->get_payment_method_title()));
    }
    $h .= '</table>';

    $h .= dlp_fe_mail_items_html($order);
    $h .= dlp_fe_mail_totals_html($order);

    $nota = trim((string) $order->get_customer_note());
    if ($nota !== '') {
        $h .= dlp_fe_mail_box('<strong>Comentarios del cliente</strong><br>' . nl2br(esc_html($nota)));
    }
    return $h;
}

/* ------------------------------------------------------------------
 * Envío de correos propios (tienda y pedidos@)
 * ------------------------------------------------------------------ */
function dlp_fe_mail_send($to, $subject, $html, $bcc = '') {
    $to = array_values(array_filter(array_map('sanitize_email', (array) $to)));
    if (!$to) {
        return false;
    }
    $from_name  = (string) get_option('woocommerce_email_from_name', 'Hamburguesas del Puente');
    $from_email = sanitize_email((string) get_option('woocommerce_email_from_address', get_option('admin_email')));
    $headers = ['Content-Type: text/html; charset=UTF-8', 'From: ' . wp_specialchars_decode($from_name, ENT_QUOTES) . ' <' . $from_email . '>'];
    $bcc = sanitize_email((string) $bcc);
    if ($bcc !== '' && !in_array($bcc, $to, true)) {
        $headers[] = 'Bcc: ' . $bcc;
    }
    return wp_mail($to, $subject, $html, $headers);
}

function dlp_fe_mail_pedidos() {
    return sanitize_email((string) dlp_fe_mail_cfg('pedidos_email', 'pedidos@delpuente.com.gt'));
}

function dlp_fe_mail_panel_url() {
    return home_url('/orders/');
}

/** Contenido del correo de tienda según el tipo: nuevo | reasignado | anterior. */
function dlp_fe_mail_tienda_html($order, $tipo = 'nuevo', $extra = []) {
    $store = dlp_fe_mail_store_info(dlp_fe_mail_store_id($order));
    if ($tipo === 'nuevo') {
        $head = 'Nuevo pedido #' . $order->get_order_number();
        $intro = dlp_fe_mail_box('<strong>' . esc_html(dlp_fe_mail_entrega_texto($order)) . '</strong> para <strong>' . esc_html($store['name']) . '</strong>. Revísalo y prepáralo desde el panel de pedidos.', 'rojo');
    } elseif ($tipo === 'reasignado') {
        $head = 'Pedido #' . $order->get_order_number() . ' reasignado a tu tienda';
        $de = !empty($extra['anterior']) ? ' Antes estaba asignado a <strong>' . esc_html($extra['anterior']) . '</strong>.' : '';
        $intro = dlp_fe_mail_box('Este pedido ahora es de <strong>' . esc_html($store['name']) . '</strong>.' . $de . ' Revísalo y prepáralo desde el panel de pedidos.', 'rojo');
    } else {
        $head = 'Pedido #' . $order->get_order_number() . ' reasignado a otra tienda';
        $intro = dlp_fe_mail_box('Este pedido pasó a <strong>' . esc_html($store['name']) . '</strong>. Ya no tienes que prepararlo.');
    }
    $icono = dlp_fe_mail_is_pickup($order) ? 'pickup' : 'delivery';
    $chip  = $tipo === 'nuevo' ? 'NUEVO PEDIDO' : 'REASIGNADO';
    $h = dlp_fe_mail_open($head, $chip, $icono) . $intro;
    $h .= dlp_fe_mail_order_html($order, ['cliente' => true, 'tienda' => false]);
    if ($tipo !== 'anterior') {
        $h .= dlp_fe_mail_button(dlp_fe_mail_panel_url(), 'Abrir el panel de pedidos');
    }
    return $h . dlp_fe_mail_close();
}

/* ------------------------------------------------------------------
 * Detalle del pago (solo aviso de pedido fallido a pedidos@)
 * Lee las notas del pedido que escribe el gateway de Cybersource y su meta.
 * Nunca muestra numero completo de tarjeta ni CVN (solo marca + ultimos 4).
 * ------------------------------------------------------------------ */
function dlp_fe_mail_luhn($n) {
    $sum = 0; $alt = false;
    for ($i = strlen($n) - 1; $i >= 0; $i--) {
        $d = (int) $n[$i];
        if ($alt) { $d *= 2; if ($d > 9) { $d -= 9; } }
        $sum += $d; $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function dlp_fe_mail_pago_limpiar($t) {
    $t = trim(wp_strip_all_tags((string) $t));
    return preg_replace_callback('/\b\d{13,19}\b/', function ($m) {
        return dlp_fe_mail_luhn($m[0]) ? '****' . substr($m[0], -4) : $m[0];
    }, $t);
}

function dlp_fe_mail_pago_razon_es($txt) {
    $mapa = [
        'Card Verification Number|CVN|CVV'         => 'Código de seguridad (CVN) inválido',
        'Insufficient Funds|insufficient'          => 'Fondos insuficientes',
        'Expired Card|expired'                     => 'Tarjeta vencida',
        'Invalid Account Number|invalid card'      => 'Número de tarjeta inválido',
        'Address Verification|AVS'                 => 'La dirección no coincide con la del banco (AVS)',
        'Decision Manager|DM |Fraud|Velocity|Score' => 'Rechazado o en revisión por antifraude',
        'Stolen|Lost Card|Pickup'                  => 'Tarjeta reportada (robo/pérdida)',
        'Do Not Honor|General decline|Processor declined|Declined' => 'El banco emisor rechazó la tarjeta',
    ];
    foreach ($mapa as $pat => $es) {
        if (preg_match('/' . $pat . '/i', $txt)) {
            return $es;
        }
    }
    return '';
}

/** Devuelve array con los datos del pago o null si no hay nada. */
function dlp_fe_mail_pago_info($order) {
    $notas = function_exists('wc_get_order_notes') ? wc_get_order_notes(['order_id' => $order->get_id(), 'type' => 'internal', 'orderby' => 'date_created', 'order' => 'DESC', 'limit' => 60]) : [];
    $fallas = [];
    foreach ($notas as $n) {
        if (preg_match('/Payment Failed|Status code|Decision Manager/i', (string) $n->content)) {
            $fallas[] = $n;
        }
    }
    $m = function ($k) use ($order) { return trim((string) $order->get_meta($k, true)); };
    $r = ['intentos' => count($fallas), 'nota' => '', 'codigo' => '', 'detalle' => '', 'tx' => '', 'fecha' => null];
    if ($fallas) {
        $txt = dlp_fe_mail_pago_limpiar($fallas[0]->content);
        $r['nota']  = $txt;
        $r['fecha'] = $fallas[0]->date_created;
        if (preg_match('/Status code\s+([A-Za-z_]+)\s*:\s*(.*?)(?:\.?\s*Transaction ID\s+(\d+)|\)\s*$|$)/si', $txt, $mm)) {
            $r['codigo']  = strtoupper($mm[1]);
            $r['detalle'] = trim($mm[2], " .)");
            $r['tx']      = isset($mm[3]) ? $mm[3] : '';
        }
        if ($r['tx'] === '' && preg_match('/Transaction ID\s+(\d+)/i', $txt, $mm)) {
            $r['tx'] = $mm[1];
        }
    }
    foreach (['_wc_cybersource_credit_card_trans_id', '_processor_transaction_id'] as $k) {
        if ($r['tx'] === '' && $m($k) !== '') { $r['tx'] = $m($k); }
    }
    $r['auth']   = $m('_authorization_code');
    $r['recon']  = $m('_reconciliation_id');
    $r['marca']  = $m('_card_type');
    $r['ult4']   = preg_match('/^\d{4}$/', $m('_account_four')) ? $m('_account_four') : '';
    $r['monto']  = $m('_authorization_amount');
    $r['trans']  = $m('_trans_date');
    $r['reint']  = $m('_retry_count');
    $r['amb']    = $m('_environment');
    $r['hay']    = $fallas || $r['tx'] !== '' || $r['marca'] !== '' || $r['ult4'] !== '' || $r['auth'] !== '';
    return $r;
}

function dlp_fe_mail_pago_html($order) {
    $i = dlp_fe_mail_pago_info($order);
    $h = '<div style="margin:0 0 6px;font-size:12px;color:#888;text-transform:uppercase;letter-spacing:.06em">Detalle del pago</div>';
    $h .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border-top:1px solid #eee;border-bottom:1px solid #eee">';
    $h .= dlp_fe_mail_row('Método', esc_html($order->get_payment_method_title()));
    if (!$i['hay']) {
        return $h . dlp_fe_mail_row('Procesador', '<em>Sin detalle del procesador</em>') . '</table>';
    }
    if ($i['tx'] !== '') {
        $h .= dlp_fe_mail_row('ID de transacción', '<span style="font-family:monospace">' . esc_html($i['tx']) . '</span>');
    }
    if ($i['codigo'] !== '' || $i['detalle'] !== '') {
        $es = dlp_fe_mail_pago_razon_es($i['detalle'] . ' ' . $i['nota']);
        $v  = '<strong>' . esc_html($i['codigo']) . '</strong>';
        if ($es !== '') { $v .= ' · ' . esc_html($es); }
        if ($i['detalle'] !== '') { $v .= '<br><span style="color:#888;font-size:12px">' . esc_html($i['detalle']) . '</span>'; }
        $h .= dlp_fe_mail_row('Estado y razón', $v);
    } elseif ($i['nota'] !== '') {
        $es = dlp_fe_mail_pago_razon_es($i['nota']);
        $h .= dlp_fe_mail_row('Razón', ($es !== '' ? esc_html($es) . '<br>' : '') . '<span style="color:#888;font-size:12px">' . esc_html($i['nota']) . '</span>');
    } else {
        $h .= dlp_fe_mail_row('Razón', '<em>Sin detalle del procesador</em>');
    }
    if ($i['marca'] !== '' || $i['ult4'] !== '') {
        $h .= dlp_fe_mail_row('Tarjeta', esc_html(trim(strtoupper($i['marca']) . ' •••• ' . $i['ult4'])));
    }
    $monto = $i['monto'] !== '' && is_numeric($i['monto']) ? (float) $i['monto'] : (float) $order->get_total();
    $h .= dlp_fe_mail_row('Monto', esc_html('Q' . number_format($monto, 2)));
    $f = $i['fecha'] ? $i['fecha']->getTimestamp() : ($i['trans'] !== '' ? strtotime($i['trans']) : 0);
    if ($f) {
        $h .= dlp_fe_mail_row('Fecha del intento', esc_html(wp_date('d/m/Y g:i a', $f)));
    }
    $h .= dlp_fe_mail_row('Intentos fallidos', esc_html((string) max($i['intentos'], (int) $i['reint'])) . ' en este pedido');
    if ($i['auth'] !== '') {
        $h .= dlp_fe_mail_row('Código de autorización', esc_html($i['auth']));
    }
    // Fallos del mismo cliente (barato: 1 consulta con limite).
    $cid = (int) $order->get_customer_id();
    if ($cid > 0) {
        $ids = wc_get_orders(['customer_id' => $cid, 'status' => 'failed', 'limit' => 20, 'return' => 'ids', 'date_created' => '>' . (time() - 30 * DAY_IN_SECONDS)]);
        $h .= dlp_fe_mail_row('Pedidos fallidos del cliente', esc_html((string) count($ids)) . ' en los últimos 30 días');
    }
    return $h . '</table>';
}

function dlp_fe_mail_aviso_html($order, $tipo) {
    $store  = dlp_fe_mail_store_info(dlp_fe_mail_store_id($order));
    $falla  = $tipo === 'fallido';
    $head   = ($falla ? 'Pedido fallido #' : 'Pedido cancelado #') . $order->get_order_number();
    $motivo = trim((string) $order->get_meta('_motivo_cancelacion_tienda', true));
    $txt    = $falla ? 'El pago de este pedido no se completó.' : 'Este pedido fue cancelado' . ($motivo !== '' ? ': ' . esc_html($motivo) : '.');
    $h = dlp_fe_mail_open($head, $falla ? 'PEDIDO FALLIDO' : 'PEDIDO CANCELADO', 'fail') . dlp_fe_mail_box($txt . ($store['name'] !== '' ? ' Tienda: <strong>' . esc_html($store['name']) . '</strong>.' : ''), 'rojo');
    $h .= dlp_fe_mail_order_html($order, ['cliente' => true, 'tienda' => false]);
    if ($falla) {
        $h .= dlp_fe_mail_pago_html($order);
    }
    $h .= dlp_fe_mail_button(admin_url('post.php?post=' . $order->get_id() . '&action=edit'), 'Ver pedido en el administrador');
    return $h . dlp_fe_mail_close();
}

/* ------------------------------------------------------------------
 * Disparadores
 * ------------------------------------------------------------------ */
function dlp_fe_mail_notify_tienda($order, $tipo = 'nuevo', $anterior_id = 0) {
    $store_id = dlp_fe_mail_store_id($order);
    $store    = dlp_fe_mail_store_info($store_id);
    $pedidos  = dlp_fe_mail_pedidos();
    $to = $store['email'] !== '' ? [$store['email']] : [];
    if (!$to) {
        $to = [$pedidos]; // sin correo de tienda: llega solo a pedidos@
    }
    $suj_base = ($tipo === 'reasignado' ? 'Pedido #' . $order->get_order_number() . ' reasignado' : 'Nuevo pedido #' . $order->get_order_number())
        . ($store['name'] !== '' ? ' · ' . $store['name'] : '') . ' · ' . dlp_fe_mail_entrega_texto($order);
    $extra = [];
    $ant = $anterior_id ? dlp_fe_mail_store_info($anterior_id) : null;
    if ($ant) {
        $extra['anterior'] = $ant['name'];
    }
    dlp_fe_mail_send($to, $suj_base, dlp_fe_mail_tienda_html($order, $tipo, $extra), $pedidos);

    if ($tipo === 'reasignado' && $ant && (int) dlp_fe_mail_cfg('avisar_anterior', 1) === 1 && $ant['email'] !== '' && $ant['email'] !== $store['email']) {
        dlp_fe_mail_send([$ant['email']], 'Pedido #' . $order->get_order_number() . ' ya no es de tu tienda', dlp_fe_mail_tienda_html($order, 'anterior'), $pedidos);
    }
}

/** Pedido nuevo en curso (efectivo al crear; tarjeta al confirmarse el pago). */
add_action('woocommerce_order_status_processing', function ($order_id, $order = null) {
    if (!(int) dlp_fe_mail_cfg('aviso_tienda', 1)) {
        return;
    }
    $order = $order ?: wc_get_order($order_id);
    if (!$order || $order->get_type() !== 'shop_order' || $order->get_meta('_dlp_mail_tienda', true) !== '' || !dlp_fe_mail_store_id($order)) {
        return;
    }
    dlp_fe_mail_notify_tienda($order, 'nuevo');
    $order->update_meta_data('_dlp_mail_tienda', dlp_fe_mail_store_id($order));
    $order->save_meta_data();
}, 20, 2);

/** Reasignación desde el panel: el pedido ya avisado a una tienda cambia de tienda. */
add_action('woocommerce_update_order', function ($order_id, $order = null) {
    static $busy = false;
    if ($busy || !(int) dlp_fe_mail_cfg('aviso_tienda', 1)) {
        return;
    }
    $order = $order ?: wc_get_order($order_id);
    if (!$order || $order->get_type() !== 'shop_order' || !in_array($order->get_status(), ['processing', 'prep', 'dlv', 'rtp'], true)) {
        return;
    }
    $notificada = (int) $order->get_meta('_dlp_mail_tienda', true);
    $actual     = dlp_fe_mail_store_id($order);
    if (!$notificada || !$actual || $notificada === $actual) {
        return;
    }
    $busy = true;
    dlp_fe_mail_notify_tienda($order, 'reasignado', $notificada);
    $order->update_meta_data('_dlp_mail_tienda', $actual);
    $order->save_meta_data();
    $busy = false;
}, 20, 2);

/** Fallido / cancelado: aviso a pedidos@ (reemplaza los correos de administrador de WooCommerce). */
foreach (['failed' => 'fallido', 'cancelled' => 'cancelado'] as $dlp_fe_mail_st => $dlp_fe_mail_tp) {
    add_action('woocommerce_order_status_' . $dlp_fe_mail_st, function ($order_id, $order = null) use ($dlp_fe_mail_tp) {
        $order = $order ?: wc_get_order($order_id);
        $pedidos = dlp_fe_mail_pedidos();
        if (!$order || $order->get_type() !== 'shop_order' || $pedidos === '') {
            return;
        }
        dlp_fe_mail_send([$pedidos], ($dlp_fe_mail_tp === 'fallido' ? 'Pedido fallido #' : 'Pedido cancelado #') . $order->get_order_number(), dlp_fe_mail_aviso_html($order, $dlp_fe_mail_tp));
    }, 20, 2);
}
unset($dlp_fe_mail_st, $dlp_fe_mail_tp);

/* ------------------------------------------------------------------
 * Vista previa (Herramientas → Vista previa de correos) y utilidades
 * ------------------------------------------------------------------ */
function dlp_fe_mail_tipos() {
    return [
        'tienda_nuevo'       => 'Tienda: nuevo pedido',
        'tienda_reasignado'  => 'Tienda: pedido reasignado (tienda nueva)',
        'tienda_anterior'    => 'Tienda: pedido reasignado (tienda anterior)',
        'aviso_fallido'      => 'pedidos@: pedido fallido',
        'aviso_cancelado'    => 'pedidos@: pedido cancelado',
        'customer_processing_order' => 'Cliente: pedido en proceso',
        'customer_completed_order'  => 'Cliente: pedido completado',
        'customer_failed_order'     => 'Cliente: pago fallido',
        'customer_on_hold_order'    => 'Cliente: pedido en espera',
        'customer_refunded_order'   => 'Cliente: reembolso',
        'customer_note'             => 'Cliente: nota del pedido',
        'customer_new_account'      => 'Cliente: cuenta nueva',
        'customer_reset_password'   => 'Cliente: restablecer contraseña',
        'customer_verify_email'     => 'Cliente: verificar correo',
    ];
}

function dlp_fe_mail_ultimo_pedido() {
    $q = wc_get_orders(['type' => 'shop_order', 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'status' => ['processing', 'completed', 'prep', 'dlv', 'rtp'], 'return' => 'ids']);
    return $q ? (int) $q[0] : 0;
}

/** HTML de un correo con datos reales (o de muestra para los de cuenta). Devuelve [asunto, html]. */
function dlp_fe_mail_render($tipo, $order_id = 0) {
    $order = wc_get_order($order_id ?: dlp_fe_mail_ultimo_pedido());
    if (strpos($tipo, 'tienda_') === 0 && $order) {
        $t = substr($tipo, 7);
        return ['Pedido #' . $order->get_order_number(), dlp_fe_mail_tienda_html($order, $t, ['anterior' => 'Tienda anterior'])];
    }
    if (strpos($tipo, 'aviso_') === 0 && $order) {
        return ['Aviso', dlp_fe_mail_aviso_html($order, substr($tipo, 6))];
    }
    $emails = WC()->mailer()->get_emails();
    $map = [];
    foreach ($emails as $e) {
        $map[$e->id] = $e;
    }
    if (!isset($map[$tipo])) {
        return ['', '<p>Correo desconocido.</p>'];
    }
    $e = $map[$tipo];
    $user = wp_get_current_user();
    if (in_array($tipo, ['customer_new_account', 'customer_reset_password', 'customer_verify_email'], true)) {
        $e->user_login = 'cliente.demo';
        $e->user_email = 'cliente.demo@example.com';
        $e->user_display_name = 'Cliente Demo';
        $e->user_id = 0;
        $e->reset_key = 'demo-key';
        $e->set_password_url = home_url('/mi-cuenta/?demo=1');
        $e->password_generated = true;
        $e->user_pass = '';
        $e->verify_url = home_url('/mi-cuenta/?verificar=demo');
        $e->object = null;
        $html = wc_get_template_html('emails/' . str_replace('_', '-', $tipo) . '.php', [
            'email_heading' => $e->get_heading(), 'user_id' => 0, 'user_login' => 'cliente.demo', 'user_email' => 'cliente.demo@example.com',
            'user_display_name' => 'Cliente Demo', 'reset_key' => 'demo-key', 'blogname' => get_bloginfo('name'),
            'set_password_url' => $e->set_password_url, 'password_generated' => true, 'user_pass' => '', 'verify_url' => $e->verify_url,
            'additional_content' => $e->get_additional_content(), 'sent_to_admin' => false, 'plain_text' => false, 'email' => $e,
        ]);
        return [$e->get_subject(), $html];
    }
    if (!$order) {
        return ['', '<p>No hay pedidos para la vista previa.</p>'];
    }
    $e->object = $order;
    $e->placeholders['{order_date}'] = wc_format_datetime($order->get_date_created());
    $e->placeholders['{order_number}'] = $order->get_order_number();
    if ($tipo === 'customer_note') {
        $e->customer_note = 'Tu pedido va en camino. ¡Gracias por la paciencia!';
    }
    if ($tipo === 'customer_refunded_order') {
        $e->partial_refund = false;
    }
    return [$e->get_subject(), $e->get_content_html()];
}

add_action('admin_menu', function () {
    add_management_page('Vista previa de correos', 'Vista previa de correos', 'manage_options', 'dlp-fe-correos', function () {
        $oid = isset($_GET['pedido']) ? absint($_GET['pedido']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        echo '<div class="wrap"><h1>Vista previa de correos</h1>';
        echo '<p>Se muestran con el pedido #' . esc_html($oid ?: dlp_fe_mail_ultimo_pedido()) . '. Modo de pruebas: <strong>' . (dlp_fe_mail_test_mode() ? 'activo (todo va a ' . esc_html(dlp_fe_mail_test_to()) . ')' : 'apagado') . '</strong>.</p>';
        echo '<form method="get"><input type="hidden" name="page" value="dlp-fe-correos">Pedido: <input type="number" name="pedido" value="' . esc_attr($oid ?: '') . '" placeholder="ID del pedido"> <button class="button">Cambiar</button></form><ul style="margin-top:14px">';
        foreach (dlp_fe_mail_tipos() as $k => $label) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=dlp_fe_mail_preview&tipo=' . $k . '&pedido=' . $oid), 'dlp_fe_mail_preview');
            echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a></li>';
        }
        echo '</ul></div>';
    });
});

add_action('admin_post_dlp_fe_mail_preview', function () {
    if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
    }
    check_admin_referer('dlp_fe_mail_preview');
    $tipo = isset($_GET['tipo']) ? sanitize_key($_GET['tipo']) : '';
    $r = dlp_fe_mail_render($tipo, isset($_GET['pedido']) ? absint($_GET['pedido']) : 0);
    header('Content-Type: text/html; charset=utf-8');
    echo $r[1]; // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
});
