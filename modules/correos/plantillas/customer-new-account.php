<?php
if (!defined('ABSPATH')) { exit; }
list($dlp_chip, $dlp_icon) = dlp_fe_mail_estado($email);
echo dlp_fe_mail_open($email_heading, $dlp_chip, $dlp_icon); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_p('Hola <strong>' . esc_html($user_display_name) . '</strong>,');
echo dlp_fe_mail_p('Creamos tu cuenta en Hamburguesas Del Puente. Con ella puedes guardar tus direcciones, repetir pedidos y ver tu historial.');
echo dlp_fe_mail_box('Tu usuario: <strong>' . esc_html($user_login) . '</strong>'); // phpcs:ignore WordPress.Security.EscapeOutput
if (!empty($password_generated) && !empty($set_password_url)) {
    echo dlp_fe_mail_p('Primero elige tu contraseña:');
    echo dlp_fe_mail_button($set_password_url, 'Crear mi contraseña'); // phpcs:ignore WordPress.Security.EscapeOutput
}
echo dlp_fe_mail_button(wc_get_page_permalink('myaccount'), 'Ir a mi cuenta'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
