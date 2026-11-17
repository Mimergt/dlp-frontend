<?php
if (!defined('ABSPATH')) { exit; }
do_action('woocommerce_email_header', $email_heading, $email);
$nom = trim($order->get_billing_first_name());
$parcial = !empty($partial_refund);
echo dlp_fe_mail_p('Hola' . ($nom !== '' ? ' <strong>' . esc_html($nom) . '</strong>' : '') . ',');
echo dlp_fe_mail_p($parcial
    ? 'Hicimos un reembolso parcial de tu pedido <strong>#' . esc_html($order->get_order_number()) . '</strong>.'
    : 'Reembolsamos tu pedido <strong>#' . esc_html($order->get_order_number()) . '</strong>.'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_box('El dinero puede tardar unos días hábiles en reflejarse, según tu banco o tarjeta.'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_order_html($order, ['cliente' => false, 'tienda' => false, 'pago' => false]); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
