<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menú principal «DLP Config» con un submenú por sección (ver DLP_FE_Registry::sections()).
 * En cada página, una pestaña por módulo. Pestañas especiales: Editor de campos (Checkout) y Bloqueos (Antifraude).
 */
class DLP_FE_Admin {
    const PARENT = 'dlp-config';

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_post_dlp_fe_save', [__CLASS__, 'save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('plugin_action_links_' . plugin_basename(DLP_FE_FILE), function ($links) {
            array_unshift($links, '<a href="' . esc_url(self::url('home')) . '">Configuración</a>');
            return $links;
        });
    }

    /** Dirección de una sección (y pestaña). */
    public static function url($section, $tab = '') {
        $slug = $section === 'home' ? self::PARENT : self::PARENT . '-' . $section;
        return admin_url('admin.php?page=' . $slug . ($tab !== '' ? '&tab=' . rawurlencode($tab) : ''));
    }

    public static function menu() {
        add_menu_page('DLP Config', 'DLP Config', 'manage_options', self::PARENT, [__CLASS__, 'render'], 'dashicons-admin-generic', 58);
        foreach (DLP_FE_Registry::sections() as $slug => $info) {
            add_submenu_page(self::PARENT, $info[0] . ' · DLP Config', $info[0], 'manage_options', $slug === 'home' ? self::PARENT : self::PARENT . '-' . $slug, [__CLASS__, 'render']);
        }
    }

    public static function assets($hook) {
        if (self::section() === null) {
            return;
        }
        wp_enqueue_style('dlpfe-panel', DLP_FE_URL . 'admin/panel.css', ['wp-color-picker'], filemtime(DLP_FE_DIR . 'admin/panel.css'));
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    /** Sección de la página actual, o null si no es una página de DLP Config. */
    private static function section() {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === self::PARENT) {
            return 'home';
        }
        if (strpos($page, self::PARENT . '-') === 0) {
            $s = substr($page, strlen(self::PARENT) + 1);
            return isset(DLP_FE_Registry::sections()[$s]) ? $s : null;
        }
        return null;
    }

    /** Pestañas de una sección: [clave => etiqueta]. Una por módulo, más las especiales. */
    private static function tabs($section) {
        $mods = array_filter(DLP_FE_Registry::all(), function ($m) use ($section) {
            return $m['section'] === $section;
        });
        uasort($mods, function ($a, $b) {
            return [$a['order'], $a['id']] <=> [$b['order'], $b['id']];
        });
        $tabs = [];
        foreach ($mods as $id => $m) {
            $tabs[$id] = $m['tab'];
        }
        if ($section === 'home' && function_exists('dlp_fe_disp_render_admin')) {
            $tabs['por-tienda'] = 'Disponibilidad por tienda';
        }
        if ($section === 'antifraude' && function_exists('dlp_fe_af_render_admin')) {
            $tabs['bloqueos'] = 'Bloqueos y actividad';
        }
        return $tabs;
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
        $section = isset($_POST['section']) ? sanitize_key(wp_unslash($_POST['section'])) : 'home';
        $tab     = isset($_POST['tab']) ? sanitize_key(wp_unslash($_POST['tab'])) : '';
        $in      = isset($_POST['m']) && is_array($_POST['m']) ? wp_unslash($_POST['m']) : [];
        $state   = get_option(DLP_FE_Registry::OPTION, []);
        foreach (DLP_FE_Registry::all() as $id => $m) {
            if ($id !== $tab) {
                continue; // solo se guarda el módulo de la pestaña abierta
            }
            $row  = isset($in[$id]) && is_array($in[$id]) ? $in[$id] : [];
            $sets = [];
            foreach ($m['schema'] as $key => $f) {
                $raw        = isset($row['settings'][$key]) ? $row['settings'][$key] : ($f['type'] === 'checkbox' ? 0 : $f['default']);
                $sets[$key] = self::clean($f, $raw);
            }
            $state[$id] = [
                'enabled'  => !empty($row['enabled']) ? 1 : 0,
                'where'    => isset($row['where']) ? sanitize_text_field($row['where']) : '',
                'settings' => $sets,
            ];
        }
        update_option(DLP_FE_Registry::OPTION, $state);
        wp_safe_redirect(self::url(isset(DLP_FE_Registry::sections()[$section]) ? $section : 'home', $tab) . '&saved=1');
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
        $section  = self::section() ?: 'home';
        $info     = DLP_FE_Registry::sections()[$section];
        $tabs     = self::tabs($section);
        $tab      = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : '';
        if (!isset($tabs[$tab])) {
            $tab = (string) key($tabs);
        }
        $mods = DLP_FE_Registry::all();
        $m    = isset($mods[$tab]) ? $mods[$tab] : null;
        ?>
        <div class="wrap dlpfe">
            <div class="dlpfe-top"><h1><?php echo esc_html($info[0]); ?></h1><small><?php echo esc_html($info[1]); ?> · DLP Config v<?php echo esc_html(DLP_FE_VERSION); ?></small></div>
            <?php if (DLP_FE_Loader::legacy_theme_active()) : ?>
                <div class="notice notice-warning inline"><p><strong>Modo espera:</strong> el tema <code>dlp</code> sigue activo y tiene su propia lógica PHP, así que los módulos de arranque están en pausa. Al activar DLP26 se cargan solos.</p></div>
            <?php endif; ?>
            <?php if (!empty($_GET['saved'])) : ?><div class="notice notice-success is-dismissible inline"><p>Guardado.</p></div><?php endif; ?>
            <?php if (count($tabs) > 1) : ?>
                <nav class="dlpfe-tabs">
                    <?php foreach ($tabs as $slug => $label) : ?>
                        <a class="<?php echo $slug === $tab ? 'on' : ''; ?>" href="<?php echo esc_url(self::url($section, $slug)); ?>"><?php echo esc_html($label); ?><?php if (isset($mods[$slug]) && !$mods[$slug]['enabled']) : ?> <em>(apagado)</em><?php endif; ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
            <div class="dlpfe-main">
            <?php
            if ($tab === 'bloqueos') {
                dlp_fe_af_render_admin();
            } elseif ($tab === 'por-tienda') {
                dlp_fe_disp_render_admin();
            } elseif ($tab === 'checkout-campos-editor' && $m && $m['enabled'] && function_exists('dlp_fe_cf_render_admin')) {
                dlp_fe_cf_render_admin();
            } elseif (!$m) {
                echo '<p><em>Todavía no hay módulos en esta sección.</em></p>';
            } else {
                self::render_module($section, $tab, $m);
            }
            ?>
            </div>
        </div>
        <?php
    }

    private static function render_module($section, $id, $m) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="dlp_fe_save">
            <input type="hidden" name="section" value="<?php echo esc_attr($section); ?>">
            <input type="hidden" name="tab" value="<?php echo esc_attr($id); ?>">
            <?php wp_nonce_field('dlp_fe_save'); ?>
            <section class="dlp-fe-mod">
                <header>
                    <label><input type="checkbox" name="m[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked($m['enabled']); ?>> Activo</label>
                    <div>
                        <h3><?php echo esc_html($m['label']); ?> <code style="font-weight:400"><?php echo esc_html($id); ?></code></h3>
                        <span class="description"><?php echo esc_html($m['description']); ?></span>
                    </div>
                </header>
                <?php
                $grupos = [];
                foreach ($m['schema'] as $f0) {
                    if ($f0['group'] !== '' && !in_array($f0['group'], $grupos, true)) {
                        $grupos[] = $f0['group'];
                    }
                }
                $conGrupos = !empty($grupos);
                if ($conGrupos) {
                    $grupos[] = 'Avanzado';
                }
                ?>
                <div class="dlp-fe-body<?php echo $conGrupos ? ' has-groups' : ''; ?>">
                    <?php if ($conGrupos) : ?>
                        <div class="dlpfe-gtabs" role="tablist">
                            <?php foreach ($grupos as $gi => $g) : ?>
                                <button type="button" role="tab" class="dlpfe-gtab<?php echo $gi === 0 ? ' on' : ''; ?>" data-g="<?php echo esc_attr($g); ?>"><?php echo esc_html($g); ?></button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="dlp-fe-row"<?php echo $conGrupos ? ' data-g="Avanzado" hidden' : ''; ?>>
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
                        <div class="dlp-fe-row"<?php echo $conGrupos ? ' data-g="' . esc_attr($f['group'] !== '' ? $f['group'] : 'Avanzado') . '"' . ($f['group'] === $grupos[0] ? '' : ' hidden') : ''; ?>>
                            <strong><?php echo esc_html($f['label']); ?></strong>
                            <div><?php self::field($id, $f, $m['values'][$key]); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php submit_button('Guardar ' . $m['tab']); ?>
        </form>
        <script>
        (function () {
          document.querySelectorAll('.dlpfe .dlp-fe-body.has-groups').forEach(function (body) {
            var mod = body.closest('.dlp-fe-mod'), key = 'dlpfe-g-' + ((mod.querySelector('header code') || {}).textContent || '');
            function show(g) {
              body.querySelectorAll('.dlpfe-gtab').forEach(function (t) { t.classList.toggle('on', t.getAttribute('data-g') === g); });
              body.querySelectorAll('.dlp-fe-row').forEach(function (r) { r.hidden = r.getAttribute('data-g') !== g; });
              try { sessionStorage.setItem(key, g); } catch (e) {}
            }
            body.addEventListener('click', function (e) { var t = e.target.closest('.dlpfe-gtab'); if (t) show(t.getAttribute('data-g')); });
            var saved = null; try { saved = sessionStorage.getItem(key); } catch (e) {}
            var first = body.querySelector('.dlpfe-gtab');
            show(saved && body.querySelector('.dlpfe-gtab[data-g="' + saved.replace(/"/g, '') + '"]') ? saved : (first ? first.getAttribute('data-g') : ''));
          });
        })();
        </script>
        <details class="dlpfe-help"><summary>Ayuda sobre los módulos</summary><p>Cada módulo es independiente: si uno falla, desactívalo y el resto del sitio sigue igual.
            Dónde se carga: <code>all, home, shop, product, cart, checkout, account, category, page:slug, post_type:tipo</code> (separados por coma; vacío = valor por defecto del módulo).
            Los ajustes de cada módulo quedan disponibles en CSS como <code>var(--dlp-modulo-clave)</code> y en JS como <code>window.dlpFE["modulo"]</code>.</p></details>
        <?php
    }
}
