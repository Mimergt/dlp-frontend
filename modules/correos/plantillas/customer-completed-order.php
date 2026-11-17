<?php
if (!defined('ABSPATH')) { exit; }
do_action('woocommerce_email_header', $email_heading, $email);
$nom = trim($order->get_billing_first_name());
echo dlp_fe_mail_p('Hola' . ($nom !== '' ? ' <strong>' . esc_html($nom) . '</strong>' : '') . ',');
echo dlp_fe_mail_p(dlp_fe_mail_is_pickup($order) ? 'Tu pedido ya fue entregado en la tienda. ¡Que lo disfrutes!' : 'Tu pedido ya fue entregado. ¡Que lo disfrutes!');
echo dlp_fe_mail_order_html($order, ['cliente' => false, 'pago' => false]); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_p('Cuéntanos qué tal estuvo respondiendo a este correo.');
echo dlp_fe_mail_button(home_url('/menu/'), 'Pedir de nuevo'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
