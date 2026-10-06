<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Página de gracias (order-received). La plantilla la imprime plantillas-woocommerce/templates/checkout/thankyou.php
 * llamando a dlp_fe_gr_render(). Estados: pending/on-hold = Recibido, processing = Preparando, dlv/rtp = En camino o Listo,
 * completed = Entregado. Sin rastreo.
 */

function dlp_fe_gr_active() {
    return function_exists('is_order_received_page') && is_order_received_page();
}

add_filter('body_class', function ($c) {
    if (dlp_fe_gr_active()) {
        $c[] = 'dlpgr-page';
    }
    return $c;
});

// Evita que pedido-recoger imprima su caja gris: aquí el botón y los mensajes van dentro del diseño
add_filter('dlp_fe_recoger_en_gracias', '__return_false');

/** 0..3 según el estado; null si no aplica (cancelado, fallido…) */
function dlp_fe_gr_step($order) {
    switch ($order->get_status()) {
        case 'pending':
        case 'on-hold':
            return 0;
        case 'processing':
            return 1;
        case 'dlv':
        case 'rtp':
            return 2;
        case 'completed':
            return 3;
    }
    return null;
}

function dlp_fe_gr_icons() {
    return [
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 11l2 2 4-4"/>',
        'cook'    => '<path class="steam" d="M9 6c-1-1.2 1-2.2 0-3.4M13 6c-1-1.2 1-2.2 0-3.4M17 6c-1-1.2 1-2.2 0-3.4"/><path d="M4 11h16v2a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6v-2Z"/><path d="M20 12h2"/>',
        'moto'    => '<g class="moto"><circle class="wheel" cx="6" cy="17" r="2.5"/><circle class="wheel" cx="18" cy="17" r="2.5"/><path d="M6 17h5l3-7h3l1 7M10 10h4M13 8h3"/></g>',
        'bag'     => '<g class="bag"><path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></g>',
        'home'    => '<g class="home"><path d="M4 11.5 12 4l8 7.5"/><path d="M6 10v9.5h12V10"/><path d="M10 19.5v-5h4v5"/></g>',
        'smile'   => '<g class="home"><circle cx="12" cy="12" r="9"/><path d="M8.5 14c1 1.6 2.2 2.2 3.5 2.2s2.5-.6 3.5-2.2M9 9.5h.01M15 9.5h.01"/></g>',
    ];
}

function dlp_fe_gr_render($order) {
    if (!$order) {
        echo '<div class="dlpgr"><div class="dlpgr-hero"><h1 class="dlpgr-t">No encontramos tu pedido</h1></div></div>';
        return;
    }
    $o       = function ($k) { return DLP_FE_Registry::setting('gracias-pagina', $k); };
    $pickup  = $order->get_meta('woofood_order_type') === 'pickup';
    $step    = dlp_fe_gr_step($order);
    $name    = trim($order->get_billing_first_name());
    $tienda  = (string) $order->get_meta('tienda_asignada');
    $hora    = (string) $order->get_meta('woofood_time_to_pickup');
    $status  = $order->get_status();
    $llego   = $status === 'rtp';

    if ($step === null) {
        $falla = in_array($status, ['failed'], true);
        $titulo = $falla ? 'No pudimos procesar tu pedido' : 'Este pedido fue cancelado';
        $texto  = $falla ? 'Hubo un problema con el pago. Intenta de nuevo o escríbenos.' : 'Si crees que es un error, comunícate con nosotros.';
    } else {
        $titulos = [
            $name !== '' ? '¡Gracias, ' . $name . '!' : '¡Gracias por tu pedido!',
            $name !== '' ? '¡Gracias, ' . $name . '!' : '¡Gracias por tu pedido!',
            $pickup ? ($llego ? '¡Ya te estamos atendiendo!' : '¡Tu pedido está listo!') : '¡Tu pedido va en camino!',
            '¡Buen provecho!',
        ];
        $titulo = $titulos[$step];
        $texto  = 'Recibimos tu pedido <b>#' . esc_html($order->get_order_number()) . '</b>.';
        if ($step === 3) {
            $texto = 'Tu pedido <b>#' . esc_html($order->get_order_number()) . '</b> se completó. Gracias por elegirnos.';
        } elseif ($pickup) {
            $texto .= '<br>' . ($tienda !== '' ? 'Recoge en ' . esc_html($tienda) : 'Recoge en el restaurante') . ($hora !== '' ? ' · ' . esc_html($hora) : '');
        } else {
            $dir = trim($order->get_billing_address_1());
            $texto .= '<br>' . ($tienda !== '' ? 'Sale de ' . esc_html($tienda) : 'Entrega a domicilio') . ($dir !== '' ? ' · ' . esc_html($dir) : '');
        }
    }

    $etapas = [
        ['Recibido', 'receipt'],
        ['Preparando', 'cook'],
        [$pickup ? 'Listo' : 'En camino', $pickup ? 'bag' : 'moto'],
        [$pickup ? 'Recogido' : 'Entregado', $pickup ? 'smile' : 'home'],
    ];
    $ic = dlp_fe_gr_icons();

    $pago  = $order->get_payment_method_title();
    $url_s = home_url($o('url_seguir') ?: '/');
    $ver   = is_user_logged_in() && (int) $order->get_user_id() === get_current_user_id() ? $order->get_view_order_url() : '';
    $ajaxu = esc_attr(admin_url('admin-ajax.php'));
    ?>
    <div class="dlpgr" data-order="<?php echo esc_attr($order->get_id()); ?>" data-key="<?php echo esc_attr($order->get_order_key()); ?>" data-step="<?php echo esc_attr((string) $step); ?>" data-status="<?php echo esc_attr($status); ?>" data-ajax="<?php echo $ajaxu; ?>" data-every="<?php echo (int) ($o('intervalo') ?: 20); ?>">
        <div class="dlpgr-hero">
            <?php if ($step !== null && $step < 3) : ?>
                <div class="dlpgr-scene"><?php echo file_get_contents(__DIR__ . '/scene.svg'); // phpcs:ignore ?></div>
            <?php endif; ?>
            <h1 class="dlpgr-t"><?php echo esc_html($titulo); ?></h1>
            <p class="dlpgr-s"><?php echo wp_kses($texto, ['b' => [], 'br' => []]); ?></p>
        </div>
        <div class="dlpgr-sheet">
            <?php if ($step !== null) : ?>
                <div class="dlpgr-steps">
                    <div class="dlpgr-fill" style="width:<?php echo esc_attr(round($step * 25.3, 1)); ?>%"></div>
                    <?php foreach ($etapas as $i => $e) :
                        $c = $i < $step ? 'done' : ($i === $step ? 'now' : '');
                        if ($i === 0 && $step > 0) {
                            $c .= ' first';
                        }
                        // el último paso, ya entregado, queda como hecho
                        if ($step === 3 && $i === 3) {
                            $c = 'done';
                        }
                        ?>
                        <div class="dlpgr-st <?php echo esc_attr($c); ?>"><div class="dlpgr-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $ic[$e[1]]; // phpcs:ignore ?></svg></div><?php echo esc_html($e[0]); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="dlpgr-card">
                <div class="dlpgr-row"><span>Total</span><b><?php echo wp_kses_post($order->get_formatted_order_total()); ?></b></div>
                <?php if ($pago) : ?><div class="dlpgr-row"><span>Pago</span><b><?php echo esc_html($pago); ?></b></div><?php endif; ?>
            </div>
            <?php
            if ($pickup && $status === 'dlv' && function_exists('dlp_fe_recoger_button')) {
                echo dlp_fe_recoger_button($order); // phpcs:ignore
            }
            if ($ver) {
                echo '<a class="dlpgr-btn" href="' . esc_url($ver) . '">Ver mi pedido</a>';
            }
            ?>
            <a class="dlpgr-btn o" href="<?php echo esc_url($url_s); ?>"><?php echo esc_html($o('texto_seguir') ?: 'Seguir pidiendo'); ?></a>
            <div class="dlpgr-extra"><?php do_action('woocommerce_thankyou', $order->get_id()); ?></div>
        </div>
    </div>
    <?php
}

// Revisión periódica del estado (para recargar cuando cambie). Exige la llave del pedido.
function dlp_fe_gr_ajax() {
    $id  = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
    $key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';
    $o   = $id ? wc_get_order($id) : false;
    if (!$o || $key === '' || !hash_equals($o->get_order_key(), $key)) {
        wp_send_json_error('forbidden', 403);
    }
    wp_send_json_success(['status' => $o->get_status()]);
}
add_action('wp_ajax_dlp_fe_gr_status', 'dlp_fe_gr_ajax');
add_action('wp_ajax_nopriv_dlp_fe_gr_status', 'dlp_fe_gr_ajax');
