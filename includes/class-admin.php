<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Apariencia → DLP Frontend. Una pestaña por sección (Home / Portada, General, Sistema).
 * En cada una: módulos (activar, dónde se carga) y los ajustes que cada módulo declare en su module.json.
 */
class DLP_FE_Admin {
    const HOOK = 'appearance_page_dlp-frontend';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_dlp_fe_save', [__CLASS__, 'save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('plugin_action_links_' . plugin_basename(DLP_FE_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(admin_url('themes.php?page=dlp-frontend')) . '">Panel</a>');
            return $links;
        });
    }

    public static function menu() {
        add_theme_page('DLP Frontend', 'DLP Frontend', 'manage_options', 'dlp-frontend', [__CLASS__, 'render']);
    }

    public static function assets($hook) {
        if ($hook !== self::HOOK) {
            return;
        }
        wp_enqueue_style('dlpfe-panel', DLP_FE_URL . 'admin/panel.css', ['wp-color-picker'], filemtime(DLP_FE_DIR . 'admin/panel.css'));
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_style('wp-color-picker', '.dlp-fe-mod{margin:0 0 16px;background:#fff;border:1px solid #c3c4c7;border-radius:4px}.dlp-fe-mod>header{display:flex;gap:12px;align-items:flex-start;padding:12px 16px;border-bottom:1px solid #f0f0f1}.dlp-fe-mod>header label{margin-top:2px}.dlp-fe-mod h3{margin:0 0 2px}.dlp-fe-body{padding:4px 16px 12px}.dlp-fe-row{display:grid;grid-template-columns:220px 1fr;gap:12px;padding:10px 0;border-bottom:1px solid #f6f7f7;align-items:start}.dlp-fe-row:last-child{border:0}.dlp-fe-row img{max-width:160px;height:auto;display:block;margin-bottom:6px;border:1px solid #dcdcde}@media(max-width:782px){.dlp-fe-row{grid-template-columns:1fr}}');
        wp_add_inline_script('wp-color-picker', "jQuery(function($){\$('.dlp-fe-color').wpColorPicker();\$(document).on('click','.dlp-fe-media',function(e){e.preventDefault();var w=\$(this).closest('.dlp-fe-img'),f=wp.media({multiple:false,library:{type:'image'}});f.on('select',function(){var u=f.state().get('selection').first().toJSON().url;w.find('input').val(u);w.find('img').attr('src',u).show();});f.open();});\$(document).on('click','.dlp-fe-media-clear',function(e){e.preventDefault();var w=\$(this).closest('.dlp-fe-img');w.find('input').val('');w.find('img').hide();});});");
    }

    private static function tab() {
        $tab = isset($_REQUEST['tab']) ? sanitize_key(wp_unslash($_REQUEST['tab'])) : 'home';
        if ($tab === 'campos' && function_exists('dlp_fe_cf_render_admin')) {
            return 'campos';
        }
        return isset(DLP_FE_Registry::sections()[$tab]) ? $tab : 'home';
    }

    private static function clean($f, $v) {
        $v = is_scalar($v) ? (string) $v : '';
        switch ($f['type']) {
            case 'textarea': return sanitize_textarea_field($v);
            case 'color':    return sanitize_hex_color($v) ?: $f['default'];
            case 'number':   return is_numeric($v) ? $v + 0 : $f['default'];
            case 'checkbox': return $v ? 1 : 0;
            case 'select':   return array_key_exists($v, (array) $f['options']) ? $v : $f['default'];
            case 'url':
            case 'image':    return esc_url_raw($v);
            default:         return sanitize_text_field($v);
        }
    }

    public static function save() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }
        check_admin_referer('dlp_fe_save');
        $tab   = self::tab();
        $in    = isset($_POST['m']) && is_array($_POST['m']) ? wp_unslash($_POST['m']) : [];
        $state = get_option(DLP_FE_Registry::OPTION, []);
        foreach (DLP_FE_Registry::all() as $id => $m) {
            if ($m['section'] !== $tab) {
                continue; // solo se tocan los módulos de la pestaña que se guarda
            }
            $row  = isset($in[$id]) && is_array($in[$id]) ? $in[$id] : [];
            $sets = [];
            foreach ($m['schema'] as $key => $f) {
                $raw         = isset($row['settings'][$key]) ? $row['settings'][$key] : ($f['type'] === 'checkbox' ? 0 : $f['default']);
                $sets[$key]  = self::clean($f, $raw);
            }
            $state[$id] = [
                'enabled'  => !empty($row['enabled']) ? 1 : 0,
                'where'    => isset($row['where']) ? sanitize_text_field($row['where']) : '',
                'settings' => $sets,
            ];
        }
        update_option(DLP_FE_Registry::OPTION, $state);
        wp_safe_redirect(admin_url('themes.php?page=dlp-frontend&tab=' . $tab . '&saved=1'));
        exit;
    }

    private static function field($id, $f, $value) {
        $name = 'm[' . esc_attr($id) . '][settings][' . esc_attr($f['key']) . ']';
        switch ($f['type']) {
            case 'textarea':
                echo '<textarea class="large-text" rows="3" name="' . $name . '">' . esc_textarea($value) . '</textarea>';
                break;
            case 'color':
                echo '<input type="text" class="dlp-fe-color" name="' . $name . '" value="' . esc_attr($value) . '" data-default-color="' . esc_attr($f['default']) . '">';
                break;
            case 'number':
                echo '<input type="number" step="any" name="' . $name . '" value="' . esc_attr($value) . '"> ' . esc_html($f['unit']);
                break;
            case 'checkbox':
                echo '<label><input type="checkbox" name="' . $name . '" value="1" ' . checked((bool) $value, true, false) . '> Sí</label>';
                break;
            case 'select':
                echo '<select name="' . $name . '">';
                foreach ((array) $f['options'] as $ov => $ol) {
                    echo '<option value="' . esc_attr($ov) . '" ' . selected((string) $value, (string) $ov, false) . '>' . esc_html($ol) . '</option>';
                }
                echo '</select>';
                break;
            case 'image':
                echo '<div class="dlp-fe-img"><img src="' . esc_url($value) . '" style="' . ($value ? '' : 'display:none') . '"><input type="hidden" name="' . $name . '" value="' . esc_attr($value) . '">'
                    . '<button type="button" class="button dlp-fe-media">Elegir imagen</button> <button type="button" class="button-link dlp-fe-media-clear">Quitar</button></div>';
                break;
            case 'url':
                echo '<input type="url" class="regular-text" name="' . $name . '" value="' . esc_attr($value) . '">';
                break;
            default:
                echo '<input type="text" class="regular-text" name="' . $name . '" value="' . esc_attr($value) . '">';
        }
        if ($f['help']) {
            echo '<p class="description">' . esc_html($f['help']) . '</p>';
        }
    }

    public static function render() {
        $tab      = self::tab();
        $sections = DLP_FE_Registry::sections();
        $mods     = $tab === 'campos' ? [] : array_filter(DLP_FE_Registry::all(), function ($m) use ($tab) {
            return $m['section'] === $tab;
        });
        ?>
        <div class="wrap dlpfe">
            <div class="dlpfe-top"><h1>DLP Frontend</h1><small>v<?php echo esc_html(DLP_FE_VERSION); ?></small></div>
            <div class="dlpfe-layout">
            <nav class="dlpfe-nav">
                <?php foreach ($sections as $slug => $label) : ?>
                    <a class="<?php echo $slug === $tab ? 'on' : ''; ?>" href="<?php echo esc_url(admin_url('themes.php?page=dlp-frontend&tab=' . $slug)); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
                <?php if (function_exists('dlp_fe_cf_render_admin')) : ?>
                    <hr><a class="<?php echo $tab === 'campos' ? 'on' : ''; ?>" href="<?php echo esc_url(admin_url('themes.php?page=dlp-frontend&tab=campos')); ?>">Campos del checkout</a>
                <?php endif; ?>
            </nav>
            <div class="dlpfe-main">
            <?php if (DLP_FE_Loader::legacy_theme_active()) : ?>
                <div class="notice notice-warning"><p><strong>Modo espera:</strong> el tema <code>dlp</code> sigue activo y tiene su propia lógica PHP, así que los módulos de arranque están en pausa. Al activar DLP26 se cargan solos.</p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success is-dismissible"><p>Guardado.</p></div><?php endif; ?>



            <p class="description">Cada módulo es independiente: si uno falla, desactívalo y el resto del sitio sigue igual.
                Dónde se carga: <code>all, home, shop, product, cart, checkout, account, category, page:slug, post_type:tipo</code> (separados por coma; vacío = valor por defecto del módulo).
                Los ajustes de cada módulo quedan disponibles en CSS como <code>var(--dlp-modulo-clave)</code> y en JS como <code>window.dlpFE["modulo"]</code>.</p>

            <?php if ($tab === 'campos') : dlp_fe_cf_render_admin(); elseif (!$mods) : ?>
                <p><em>Todavía no hay módulos en esta sección.</em></p>
            <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="dlp_fe_save">
                <input type="hidden" name="tab" value="<?php echo esc_attr($tab); ?>">
                <?php wp_nonce_field('dlp_fe_save'); ?>
                <?php foreach ($mods as $id => $m) : ?>
                    <section class="dlp-fe-mod">
                        <header>
                            <label><input type="checkbox" name="m[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked($m['enabled']); ?>> Activo</label>
                            <div>
                                <h3><?php echo esc_html($m['label']); ?> <code style="font-weight:400"><?php echo esc_html($id); ?></code></h3>
                                <span class="description"><?php echo esc_html($m['description']); ?></span>
                            </div>
                        </header>
                        <div class="dlp-fe-body">
                            <div class="dlp-fe-row">
                                <strong>Dónde se carga</strong>
                                <div>
                                <?php if ($m['boot'] && !$m['css'] && !$m['js']) : ?>
                                    <em>Siempre (lógica de arranque)</em>
                                <?php else : ?>
                                    <input type="text" class="regular-text" name="m[<?php echo esc_attr($id); ?>][where]" value="<?php echo esc_attr($m['where_raw']); ?>" placeholder="<?php echo esc_attr($m['where_def']); ?>">
                                <?php endif; ?>
                                </div>
                            </div>
                            <?php foreach ($m['schema'] as $key => $f) : ?>
                                <div class="dlp-fe-row">
                                    <strong><?php echo esc_html($f['label']); ?></strong>
                                    <div><?php self::field($id, $f, $m['values'][$key]); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
                <?php submit_button('Guardar ' . $sections[$tab]); ?>
            </form>
            <?php endif; ?>
            </div>
            </div>
        </div>
        <?php
    }
}
