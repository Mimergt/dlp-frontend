<?php
if (!defined('ABSPATH')) { exit; }
list($dlp_chip, $dlp_icon) = dlp_fe_mail_estado($email);
echo dlp_fe_mail_open($email_heading, $dlp_chip, $dlp_icon); // phpcs:ignore WordPress.Security.EscapeOutput
$nom = trim($order->get_billing_first_name());
echo dlp_fe_mail_p('Hola' . ($nom !== '' ? ' <strong>' . esc_html($nom) . '</strong>' : '') . ',');
echo dlp_fe_mail_box('No pudimos procesar el pago de tu pedido <strong>#' . esc_html($order->get_order_number()) . '</strong>, así que todavía no lo estamos preparando. No se te cobró nada si el banco no aprobó el pago.', 'rojo'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_order_html($order, ['cliente' => false, 'tienda' => false]); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_p('Puedes intentar de nuevo con la misma tarjeta u otra forma de pago:');
echo dlp_fe_mail_button($order->get_checkout_payment_url(), 'Reintentar el pago'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
