<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Antifraude y bloqueo de clientes.
 * Identidades: correo, teléfono (últimos 8 dígitos), cuenta (user) e IP (opcional). Sirve también para invitados.
 * Opciones: dlp_fe_af_bloqueos (lista), dlp_fe_af_eventos (intentos recientes, se podan solos).
 * Compatibilidad: al bloquear una cuenta se marca también `is_active = n` (plugin User Blocker) para impedir el login.
 */

function dlp_fe_af_cfg($key, $fallback = '') {
    return DLP_FE_Registry::setting('antifraude', $key, $fallback);
}

function dlp_fe_af_norm($tipo, $v) {
    $v = trim((string) $v);
    switch ($tipo) {
        case 'email': return strtolower(sanitize_email($v));
        case 'tel':   $d = preg_replace('/\D+/', '', $v); return strlen($d) >= 8 ? substr($d, -8) : '';
        case 'user':  return (string) absint($v) ?: '';
        case 'ip':    return filter_var($v, FILTER_VALIDATE_IP) ? $v : '';
    }
    return '';
}

function dlp_fe_af_ip() {
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
}

/** Identidades de un pedido o del checkout: [tipo => valor]. */
function dlp_fe_af_ids($email, $tel, $user, $ip) {
    $ids = [
        'email' => dlp_fe_af_norm('email', $email),
        'tel'   => dlp_fe_af_norm('tel', $tel),
        'user'  => dlp_fe_af_norm('user', $user),
    ];
    if (dlp_fe_af_cfg('por_ip', 0)) {
        $ids['ip'] = dlp_fe_af_norm('ip', $ip);
    }
    return array_filter($ids);
}

function dlp_fe_af_ids_order($order) {
    return dlp_fe_af_ids($order->get_billing_email(), $order->get_billing_phone(), $order->get_customer_id(), $order->get_customer_ip_address());
}

function dlp_fe_af_list() {
    $l = get_option('dlp_fe_af_bloqueos', []);
    return is_array($l) ? $l : [];
}

/** Bloqueos vigentes (los vencidos se descartan). */
function dlp_fe_af_active() {
    $l = dlp_fe_af_list();
    $n = time();
    $out = array_filter($l, function ($b) use ($n) {
        return empty($b['hasta']) || $b['hasta'] > $n;
    });
    if (count($out) !== count($l)) {
        update_option('dlp_fe_af_bloqueos', $out, false);
    }
    return $out;
}

/** $horas = 0 → permanente. */
function dlp_fe_af_block(array $ids, $motivo, $horas = 0, $origen = 'manual') {
    $l = dlp_fe_af_active();
    foreach ($ids as $tipo => $v) {
        $v = dlp_fe_af_norm($tipo, $v);
        if ($v === '') {
            continue;
        }
        $l[$tipo . ':' . $v] = [
            'tipo'   => $tipo,
            'valor'  => $v,
            'motivo' => sanitize_text_field($motivo),
            'desde'  => time(),
            'hasta'  => $horas > 0 ? time() + (int) round($horas * HOUR_IN_SECONDS) : 0,
            'origen' => sanitize_text_field($origen),
        ];
        if ($tipo === 'user' && !$horas) {
            update_user_meta((int) $v, 'is_active', 'n');
            update_user_meta((int) $v, 'block_msg_permenant', (string) dlp_fe_af_cfg('mensaje'));
        }
    }
    update_option('dlp_fe_af_bloqueos', $l, false);
}

function dlp_fe_af_unblock(array $ids) {
    $l = dlp_fe_af_list();
    foreach ($ids as $tipo => $v) {
        $v = dlp_fe_af_norm($tipo, $v);
        unset($l[$tipo . ':' . $v]);
        if ($tipo === 'user' && $v) {
            delete_user_meta((int) $v, 'is_active');
        }
    }
    update_option('dlp_fe_af_bloqueos', $l, false);
}

/** ¿Alguna identidad está bloqueada? Devuelve el bloqueo o null. */
function dlp_fe_af_blocked(array $ids) {
    $act = dlp_fe_af_active();
    foreach ($ids as $tipo => $v) {
        if (isset($act[$tipo . ':' . $v])) {
            return $act[$tipo . ':' . $v];
        }
    }
    return null;
}

/** API para el panel de pedidos. */
function dlp_fe_af_order_blocked($order) {
    return (bool) dlp_fe_af_blocked(dlp_fe_af_ids_order($order));
}

function dlp_fe_af_order_set($order, $bloquear, $origen = 'panel') {
    $ids = [
        'email' => $order->get_billing_email(),
        'tel'   => $order->get_billing_phone(),
        'user'  => $order->get_customer_id(),
    ];
    if ($bloquear) {
        dlp_fe_af_block($ids, 'Bloqueado por ' . $origen . ' (pedido #' . $order->get_id() . ')', 0, $origen);
    } else {
        // Al desbloquear se quita también cualquier IP guardada de este pedido.
        $ids['ip'] = $order->get_customer_ip_address();
        dlp_fe_af_unblock($ids);
    }
}

/* ---------- Eventos (intentos recientes) ---------- */

function dlp_fe_af_eventos() {
    $e = get_option('dlp_fe_af_eventos', []);
    $corte = time() - DAY_IN_SECONDS;
    return array_values(array_filter(is_array($e) ? $e : [], function ($x) use ($corte) {
        return $x['t'] >= $corte;
    }));
}

function dlp_fe_af_evento_add($kind, $order) {
    $e = dlp_fe_af_eventos();
    $e[] = [
        't'  => time(),
        'k'  => $kind,
        'o'  => $order->get_id(),
        'ids' => dlp_fe_af_ids_order($order),
    ];
    update_option('dlp_fe_af_eventos', array_slice($e, -300), false);
}

function dlp_fe_af_coincide($a, $b) {
    foreach ($a as $tipo => $v) {
        if (isset($b[$tipo]) && $b[$tipo] === $v) {
            return true;
        }
    }
    return false;
}

/* ---------- Checkout: rechazar antes de crear el pedido ---------- */

add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    $ids = dlp_fe_af_ids($data['billing_email'] ?? '', $data['billing_phone'] ?? '', get_current_user_id(), dlp_fe_af_ip());
    if (!$ids) {
        return;
    }
    $msg = (string) dlp_fe_af_cfg('mensaje');
    if (dlp_fe_af_blocked($ids)) {
        $errors->add('dlp_bloqueado', $msg);
        return;
    }
    $seg = (int) dlp_fe_af_cfg('rapido_seg', 60);
    if ($seg > 0) {
        foreach (dlp_fe_af_eventos() as $ev) {
            if ($ev['k'] === 'order' && $ev['t'] >= time() - $seg && dlp_fe_af_coincide($ids, $ev['ids'])) {
                $min = (float) dlp_fe_af_cfg('rapido_bloqueo', 30);
                if ($min > 0) {
                    dlp_fe_af_block($ids, 'Segundo pedido en menos de ' . $seg . ' s', $min / 60, 'auto');
                }
                $errors->add('dlp_bloqueado', $msg);
                return;
            }
        }
    }
}, 10, 2);

/** Registra pedidos creados (para detectar pedidos seguidos). */
add_action('woocommerce_checkout_order_processed', function ($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        dlp_fe_af_evento_add('order', $order);
    }
}, 20);

/** Pago con tarjeta fallido: cuenta el intento y bloquea al llegar al máximo. */
add_action('woocommerce_order_status_failed', function ($order_id, $order = null) {
    $order = $order ?: wc_get_order($order_id);
    if (!$order || $order->get_payment_method() === 'cod') {
        return;
    }
    // Un pedido fallido no cuenta como "pedido reciente": reintentar la tarjeta es legítimo.
    $e = array_values(array_filter(dlp_fe_af_eventos(), function ($x) use ($order_id) {
        return !($x['k'] === 'order' && (int) $x['o'] === (int) $order_id);
    }));
    update_option('dlp_fe_af_eventos', $e, false);
    dlp_fe_af_evento_add('fail', $order);

    $max = (int) dlp_fe_af_cfg('fallos_max', 3);
    if ($max < 1) {
        return;
    }
    $ids   = dlp_fe_af_ids_order($order);
    $desde = time() - (int) dlp_fe_af_cfg('fallos_ventana', 60) * MINUTE_IN_SECONDS;
    $n = 0;
    foreach (dlp_fe_af_eventos() as $ev) {
        if ($ev['k'] === 'fail' && $ev['t'] >= $desde && dlp_fe_af_coincide($ids, $ev['ids'])) {
            $n++;
        }
    }
    if ($n >= $max) {
        dlp_fe_af_block($ids, $n . ' pagos con tarjeta fallidos', (float) dlp_fe_af_cfg('fallos_bloqueo', 24), 'auto');
        $order->add_order_note('Antifraude: cliente bloqueado tras ' . $n . ' pagos con tarjeta fallidos.');
    }
}, 10, 2);

/** Reintentar el pago de un pedido (página order-pay) no pasa por la validación del checkout. */
add_filter('woocommerce_available_payment_gateways', function ($g) {
    if (is_admin() || !function_exists('is_wc_endpoint_url') || !is_wc_endpoint_url('order-pay')) {
        return $g;
    }
    $ids = dlp_fe_af_ids('', '', get_current_user_id(), dlp_fe_af_ip());
    $key = absint(get_query_var('order-pay'));
    if ($key && ($o = wc_get_order($key))) {
        $ids = array_merge($ids, dlp_fe_af_ids_order($o));
    }
    return dlp_fe_af_blocked($ids) ? [] : $g;
});

/* ---------- Administración ---------- */

add_action('admin_post_dlp_fe_af', function () {
    if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
    }
    check_admin_referer('dlp_fe_af');
    $op = isset($_POST['op']) ? sanitize_key(wp_unslash($_POST['op'])) : '';
    if ($op === 'add') {
        $tipo  = isset($_POST['tipo']) ? sanitize_key(wp_unslash($_POST['tipo'])) : '';
        $valor = isset($_POST['valor']) ? sanitize_text_field(wp_unslash($_POST['valor'])) : '';
        if ($tipo === 'user' && !ctype_digit($valor) && ($u = get_user_by('login', $valor) ?: get_user_by('email', $valor))) {
            $valor = $u->ID;
        }
        $horas = isset($_POST['horas']) ? (float) $_POST['horas'] : 0;
        $motivo = isset($_POST['motivo']) ? sanitize_text_field(wp_unslash($_POST['motivo'])) : '';
        dlp_fe_af_block([$tipo => $valor], $motivo !== '' ? $motivo : 'Manual', $horas, 'manual');
    } elseif ($op === 'del') {
        $k = isset($_POST['k']) ? sanitize_text_field(wp_unslash($_POST['k'])) : '';
        [$tipo, $valor] = array_pad(explode(':', $k, 2), 2, '');
        dlp_fe_af_unblock([$tipo => $valor]);
    }
    wp_safe_redirect(DLP_FE_Admin::url('antifraude', 'bloqueos') . '&saved=1');
    exit;
});

function dlp_fe_af_render_admin() {
    $act  = dlp_fe_af_active();
    $tipos = ['email' => 'Correo', 'tel' => 'Teléfono', 'user' => 'Cuenta (ID, usuario o correo)', 'ip' => 'IP'];
    echo '<h2>Bloqueos y antifraude</h2>';
    echo '<p class="description">Los clientes bloqueados no pueden crear pedidos (aunque compren sin cuenta). Las reglas automáticas se ajustan en la pestaña <strong>Reglas</strong>. Desde el panel de pedidos, «Bloquear cliente» agrega aquí su correo, teléfono y cuenta.</p>';
    echo '<h3>Bloqueados ahora (' . count($act) . ')</h3>';
    if (!$act) {
        echo '<p><em>Nadie está bloqueado.</em></p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Tipo</th><th>Valor</th><th>Motivo</th><th>Origen</th><th>Desde</th><th>Hasta</th><th></th></tr></thead><tbody>';
        foreach ($act as $k => $b) {
            echo '<tr><td>' . esc_html($tipos[$b['tipo']] ?? $b['tipo']) . '</td><td>' . esc_html($b['valor']) . '</td><td>' . esc_html($b['motivo']) . '</td><td>' . esc_html($b['origen']) . '</td>'
                . '<td>' . esc_html(wp_date('d/m/Y H:i', $b['desde'])) . '</td><td>' . ($b['hasta'] ? esc_html(wp_date('d/m/Y H:i', $b['hasta'])) : 'Permanente') . '</td>'
                . '<td><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">' . wp_nonce_field('dlp_fe_af', '_wpnonce', true, false)
                . '<input type="hidden" name="action" value="dlp_fe_af"><input type="hidden" name="op" value="del"><input type="hidden" name="k" value="' . esc_attr($k) . '">'
                . '<button class="button button-small">Desbloquear</button></form></td></tr>';
        }
        echo '</tbody></table>';
    }
    echo '<h3>Bloquear manualmente</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
    wp_nonce_field('dlp_fe_af');
    echo '<input type="hidden" name="action" value="dlp_fe_af"><input type="hidden" name="op" value="add"><select name="tipo">';
    foreach ($tipos as $k => $l) {
        echo '<option value="' . esc_attr($k) . '">' . esc_html($l) . '</option>';
    }
    echo '</select><input type="text" name="valor" class="regular-text" placeholder="Valor" required><input type="number" step="any" min="0" name="horas" value="0" style="width:90px" title="Horas (0 = permanente)"> horas (0 = permanente)<input type="text" name="motivo" placeholder="Motivo"><button class="button button-primary">Bloquear</button></form>';

    $ev = array_reverse(dlp_fe_af_eventos());
    echo '<h3>Actividad de las últimas 24 h</h3>';
    if (!$ev) {
        echo '<p><em>Sin actividad registrada.</em></p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Hora</th><th>Evento</th><th>Pedido</th><th>Cliente</th></tr></thead><tbody>';
        foreach (array_slice($ev, 0, 50) as $x) {
            echo '<tr><td>' . esc_html(wp_date('d/m H:i:s', $x['t'])) . '</td><td>' . ($x['k'] === 'fail' ? 'Pago fallido' : 'Pedido creado') . '</td><td>#' . (int) $x['o'] . '</td><td>' . esc_html(implode(' · ', $x['ids'])) . '</td></tr>';
        }
        echo '</tbody></table>';
    }
}
