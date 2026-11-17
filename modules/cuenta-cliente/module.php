<?php
if (!defined('ABSPATH')) {
    exit;
}

// Teléfono en "Editar cuenta".
add_action('woocommerce_edit_account_form', function () {
    $user = wp_get_current_user();
    ?>
    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
        <label for="billing_mobile_phone"><?php esc_html_e('Teléfono', 'woocommerce'); ?> <span class="required">*</span></label>
        <input type="text" class="woocommerce-Input woocommerce-Input--phone input-text" name="billing_phone" id="billing_mobile_phone" value="<?php echo esc_attr($user->billing_phone); ?>" />
    </p>
    <?php
});

add_action('woocommerce_save_account_details_errors', function ($errors) {
    if (isset($_POST['billing_phone']) && $_POST['billing_phone'] === '') {
        $errors->add('billing_phone_error', __('Por favor ingrese su teléfono', 'woocommerce'));
    }
}, 20);

add_action('woocommerce_save_account_details', function ($user_id) {
    if (!empty($_POST['billing_phone'])) {
        update_user_meta($user_id, 'billing_phone', sanitize_text_field(wp_unslash($_POST['billing_phone'])));
    }
}, 20);

// Tras recuperar / cambiar la contraseña ya no hay alert() del navegador:
// - «te enviamos un correo» lo muestra la pantalla propia (módulo recuperar-contrasena);
// - al cambiarla, vuelve a Ingresar con un aviso (?clave=ok).
add_action('init', function () {
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (strpos($uri, '/mi-cuenta/?password-reset=true') !== false) {
        wp_safe_redirect(home_url('/ingresar/?clave=ok'));
        exit;
    }
});
