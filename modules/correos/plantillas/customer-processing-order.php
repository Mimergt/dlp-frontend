<?php
if (!defined('ABSPATH')) { exit; }
list($dlp_chip, $dlp_icon) = dlp_fe_mail_estado($email);
echo dlp_fe_mail_open($email_heading, $dlp_chip, $dlp_icon); // phpcs:ignore WordPress.Security.EscapeOutput
$nom = trim($order->get_billing_first_name());
echo dlp_fe_mail_p('Hola' . ($nom !== '' ? ' <strong>' . esc_html($nom) . '</strong>' : '') . ',');
echo dlp_fe_mail_p('Recibimos tu pedido y ya lo estamos preparando. Aquí tienes el resumen:');
echo dlp_fe_mail_order_html($order, ['cliente' => false]); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_box(dlp_fe_mail_is_pickup($order) ? 'Pasa por tu pedido a la tienda indicada. Si necesitas algo, escríbenos con tu número de pedido.' : 'Te lo llevamos a la dirección indicada. Si necesitas algo, escríbenos con tu número de pedido.'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
