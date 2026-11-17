<?php
if (!defined('ABSPATH')) { exit; }
?>
<div class="dlprc">
    <div class="dlprc-hero"><h1>¿Olvidaste tu contraseña?</h1><p>Escribe el correo de tu cuenta y te enviamos un enlace para crear una nueva.</p></div>
    <form method="post" class="woocommerce-ResetPassword lost_reset_password dlprc-form" novalidate>
        <?php do_action('woocommerce_before_lost_password_form'); // avisos de WooCommerce (error de usuario, enlace vencido) ?>
        <label for="user_login">Correo electrónico</label>
        <input type="text" name="user_login" id="user_login" autocomplete="username" inputmode="email" required aria-required="true" value="<?php echo isset($_POST['user_login']) ? esc_attr(wp_unslash($_POST['user_login'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification ?>">
        <?php do_action('woocommerce_lostpassword_form'); ?>
        <input type="hidden" name="wc_reset_password" value="true">
        <button type="submit" class="dlprc-go" value="Reset password">Enviar enlace</button>
        <?php wp_nonce_field('lost_password', 'woocommerce-lost-password-nonce'); ?>
        <a class="dlprc-back" href="<?php echo esc_url(dlp_fe_rc_login_url()); ?>">Volver a ingresar</a>
    </form>
</div>
<?php do_action('woocommerce_after_lost_password_form'); ?>
