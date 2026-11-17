<?php
if (!defined('ABSPATH')) { exit; }
do_action('woocommerce_email_header', $email_heading, $email);
echo dlp_fe_mail_p('Hola <strong>' . esc_html($user_display_name) . '</strong>,');
echo dlp_fe_mail_p('Confirma que <strong>' . esc_html($user_email) . '</strong> es tu correo y vincularemos tus pedidos anteriores a tu cuenta.'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_button($verify_url, 'Confirmar mi correo'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_p('<span style="color:#888;font-size:13px">Si no lo solicitaste, ignora este correo.</span>');
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
