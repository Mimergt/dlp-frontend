<?php
if (!defined('ABSPATH')) { exit; }
$k = isset($key) ? $key : (isset($args['key']) ? $args['key'] : '');
$l = isset($login) ? $login : (isset($args['login']) ? $args['login'] : '');
$min = (int) DLP_FE_Registry::setting('recuperar-contrasena', 'min', 8);
?>
<div class="dlprc">
    <div class="dlprc-hero"><h1>Crea tu nueva contraseña</h1><p>Elige una contraseña que recuerdes<?php echo $l !== '' ? ' para <strong>' . esc_html($l) . '</strong>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>.</p></div>
    <form method="post" class="woocommerce-ResetPassword lost_reset_password dlprc-form" novalidate>
        <div class="dlprc-msg" role="alert" hidden></div>
        <?php do_action('woocommerce_before_reset_password_form'); ?>
        <?php dlp_fe_rc_pw('password_1', 'password_1', 'Nueva contraseña'); ?>
        <small class="dlprc-hint">Mínimo <?php echo (int) $min; ?> caracteres.</small>
        <?php dlp_fe_rc_pw('password_2', 'password_2', 'Repite la contraseña'); ?>
        <input type="hidden" name="reset_key" value="<?php echo esc_attr($k); ?>">
        <input type="hidden" name="reset_login" value="<?php echo esc_attr($l); ?>">
        <?php do_action('woocommerce_resetpassword_form'); ?>
        <input type="hidden" name="wc_reset_password" value="true">
        <button type="submit" class="dlprc-go" value="Save">Guardar contraseña</button>
        <?php wp_nonce_field('reset_password', 'woocommerce-reset-password-nonce'); ?>
    </form>
</div>
<?php do_action('woocommerce_after_reset_password_form'); ?>
