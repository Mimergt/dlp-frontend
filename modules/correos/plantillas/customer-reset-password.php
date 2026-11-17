<?php
if (!defined('ABSPATH')) { exit; }
list($dlp_chip, $dlp_icon) = dlp_fe_mail_estado($email);
echo dlp_fe_mail_open($email_heading, $dlp_chip, $dlp_icon); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_p('Hola <strong>' . esc_html($user_display_name ?? $user_login) . '</strong>,');
echo dlp_fe_mail_p('Recibimos una solicitud para restablecer la contraseña de tu cuenta <strong>' . esc_html($user_login) . '</strong>.'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_button(add_query_arg(['key' => $reset_key, 'id' => $user_id, 'login' => rawurlencode($user_login)], wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount'))), 'Restablecer contraseña'); // phpcs:ignore WordPress.Security.EscapeOutput
echo dlp_fe_mail_p('<span style="color:#888;font-size:13px">Si no fuiste tú, ignora este correo: tu contraseña no cambia.</span>');
echo dlp_fe_mail_extra($email, $additional_content ?? ''); // phpcs:ignore WordPress.Security.EscapeOutput
do_action('woocommerce_email_footer', $email);
