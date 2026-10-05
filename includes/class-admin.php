<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Ajustes → DLP Frontend: activar/desactivar cada módulo y cambiar en qué páginas se carga. */
class DLP_FE_Admin {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_dlp_fe_save', [__CLASS__, 'save']);
    }

    public static function menu() {
        add_options_page('DLP Frontend', 'DLP Frontend', 'manage_options', 'dlp-frontend', [__CLASS__, 'render']);
    }

    public static function save() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('dlp_fe_save');
        $in    = isset($_POST['m']) && is_array($_POST['m']) ? wp_unslash($_POST['m']) : [];
        $state = [];
        foreach (DLP_FE_Registry::all() as $id => $m) {
            $row = isset($in[$id]) && is_array($in[$id]) ? $in[$id] : [];
            $state[$id] = [
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'where'   => isset($row['where']) ? sanitize_text_field($row['where']) : '',
            ];
        }
        update_option(DLP_FE_Registry::OPTION, $state);
        wp_safe_redirect(admin_url('options-general.php?page=dlp-frontend&saved=1'));
        exit;
    }

    public static function render() {
        ?>
        <div class="wrap">
            <h1>DLP Frontend <small>v<?php echo esc_html(DLP_FE_VERSION); ?></small></h1>
            <?php if (DLP_FE_Loader::legacy_theme_active()) : ?>
                <div class="notice notice-warning"><p><strong>Modo espera:</strong> el tema <code>dlp</code> sigue activo y tiene su propia lógica PHP, así que los módulos de arranque están en pausa para no duplicarla. Al activar el tema DLP26 se cargan solos.</p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success"><p>Guardado.</p></div><?php endif; ?>
            <p>Cada módulo es independiente: si uno falla, desactívalo aquí y el resto del sitio sigue igual.
               Dónde: <code>all, home, shop, product, cart, checkout, account, category, page:slug, post_type:tipo</code> (separados por coma; vacío = el valor por defecto del módulo).</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dlp_fe_save">
                <?php wp_nonce_field('dlp_fe_save'); ?>
                <table class="widefat striped">
                    <thead><tr><th style="width:70px">Activo</th><th>Módulo</th><th>Dónde se carga</th></tr></thead>
                    <tbody>
                    <?php foreach (DLP_FE_Registry::all() as $id => $m) : ?>
                        <tr>
                            <td><input type="checkbox" name="m[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked($m['enabled']); ?>></td>
                            <td><strong><?php echo esc_html($m['label']); ?></strong> <code><?php echo esc_html($id); ?></code><br><span class="description"><?php echo esc_html($m['description']); ?></span></td>
                            <td><?php if ($m['boot']) : ?>
                                <em>Siempre (lógica de arranque)</em>
                            <?php else : ?>
                                <input type="text" class="regular-text" name="m[<?php echo esc_attr($id); ?>][where]" value="<?php echo esc_attr($m['where_raw']); ?>" placeholder="<?php echo esc_attr($m['where_def']); ?>">
                            <?php endif; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button('Guardar'); ?>
            </form>
        </div>
        <?php
    }
}
