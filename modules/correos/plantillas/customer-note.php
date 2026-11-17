<?php
if (!defined('ABSPATH')) { exit; }
list($dlp_chip, $dlp_icon) = dlp_fe_mail_estado($email);
echo dlp_fe_mail_open($email_heading, $dlp_chip, $dlp_icon); // phpcs:ignore WordPress.Security.EscapeOutput
$nom = trim($order->get_billing_first_name());
echo dlp_fe_mail_p('Hola' . ($nom !== '' ? ' <strong>' . esc_html($nom) . '</strong>' : '') . ',');
echo dlp_fe_mail_p('Dejamos una nota en tu pedido <strong>#' . esc_html($order->get_order_number()) . '</strong>:'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_box('<span style="font-size:16px">' . wp_kses_post(wpautop(wptexturize($customer_note))) . '</span>', 'rojo'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_order_html($order, ['cliente' => false, 'tienda' => false, 'pago' => false]); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
