<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Carga los módulos activos que aplican a la página. Cada módulo va aislado:
 * un archivo roto (PHP, JSON, CSS o JS) solo afecta a ese módulo.
 *
 * Solo para administradores (prueba sin cambiar nada para los visitantes):
 *   ?dlp_fe_off=all            apaga todos los módulos en esa vista
 *   ?dlp_fe_off=id1,id2        apaga esos módulos en esa vista
 */
class DLP_FE_Loader {
    private static $loaded = [];

    public static function init() {
        add_action('after_setup_theme', [__CLASS__, 'boot'], 0);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue'], 20);
        add_action('admin_bar_menu', [__CLASS__, 'admin_bar'], 100);
    }

    /** Mientras el tema legacy "dlp" siga activo, la lógica PHP vive en él: no se duplica. */
    public static function legacy_theme_active() {
        return get_stylesheet() === 'dlp';
    }

    /**
     * Módulos "boot" (lógica PHP que debe estar siempre cargada, p. ej. login de la app, campos del checkout).
     * Se cargan donde antes se cargaba el functions.php del tema. Un módulo que falla no tumba a los demás.
     * Emergencia: define('DLP_FE_DISABLED_MODULES', 'id1,id2'); en wp-config.php.
     */
    public static function boot() {
        if (self::legacy_theme_active()) {
            return;
        }
        $off = defined('DLP_FE_DISABLED_MODULES') ? array_map('trim', explode(',', (string) DLP_FE_DISABLED_MODULES)) : [];
        foreach (DLP_FE_Registry::all() as $id => $m) {
            if (!$m['boot'] || !$m['enabled'] || in_array($id, $off, true)) {
                continue;
            }
            $file = $m['dir'] . '/' . $m['php'];
            if (!$m['php'] || !is_readable($file)) {
                continue;
            }
            try {
                require_once $file;
            } catch (\Throwable $e) {
                error_log('[dlp-frontend] módulo boot "' . $id . '" falló: ' . $e->getMessage());
            }
        }
    }

    private static function forced_off() {
        if (!current_user_can('manage_options') || empty($_GET['dlp_fe_off'])) {
            return [];
        }
        return array_filter(array_map('sanitize_key', explode(',', wp_unslash($_GET['dlp_fe_off']))));
    }

    public static function enqueue() {
        $off = self::forced_off();
        foreach (DLP_FE_Registry::all() as $id => $m) {
            if (($m['boot'] && !$m['css'] && !$m['js']) || !$m['enabled'] || in_array('all', $off, true) || in_array($id, $off, true)) {
                continue;
            }
            try {
                if (!DLP_FE_Registry::matches($m['where'])) {
                    continue;
                }
                if ($m['php'] && is_readable($m['dir'] . '/' . $m['php'])) {
                    require_once $m['dir'] . '/' . $m['php'];
                }
                $base = DLP_FE_URL . 'modules/' . $id . '/';
                if ($m['css'] && is_readable($m['dir'] . '/' . $m['css'])) {
                    wp_enqueue_style('dlp-fe-' . $id, $base . $m['css'], [], filemtime($m['dir'] . '/' . $m['css']));
                }
                if ($m['js'] && is_readable($m['dir'] . '/' . $m['js'])) {
                    wp_enqueue_script('dlp-fe-' . $id, $base . $m['js'], [], filemtime($m['dir'] . '/' . $m['js']), true);
                }
                self::output_settings($id, $m);
                self::$loaded[] = $id;
            } catch (\Throwable $e) {
                error_log('[dlp-frontend] módulo "' . $id . '" falló: ' . $e->getMessage());
            }
        }
    }

    /** Valor seguro para usar dentro de una variable CSS. */
    private static function css_value($f, $v) {
        switch ($f['type']) {
            case 'color':    return sanitize_hex_color((string) $v) ?: '';
            case 'number':   return is_numeric($v) ? ($v + 0) . $f['unit'] : '';
            case 'checkbox': return $v ? '1' : '0';
            case 'image':    return $v ? 'url("' . esc_url_raw((string) $v) . '")' : 'none';
            default:         return preg_replace('/[^\p{L}\p{N}\s#%.,()\/_-]/u', '', (string) $v);
        }
    }

    /** Ajustes del módulo → variables CSS (--dlp-<modulo>-<clave>) y objeto JS (window.dlpFE["<modulo>"]). */
    private static function output_settings($id, $m) {
        if (empty($m['schema'])) {
            return;
        }
        $handle = 'dlp-fe-' . $id;
        // Clases en <body>: "body_class": "dlp-cat" + valor elegido → dlp-cat--burbujas
        foreach ($m['schema'] as $key => $f) {
            if ($f['body_class'] && $m['values'][$key] !== '' && $m['values'][$key] !== 'clasico') {
                $cls = $f['body_class'] . '--' . sanitize_html_class((string) $m['values'][$key]);
                add_filter('body_class', function ($c) use ($cls) {
                    $c[] = $cls;
                    return $c;
                });
            }
        }
        $vars   = '';
        $js     = [];
        foreach ($m['schema'] as $key => $f) {
            $v = $m['values'][$key];
            if ($f['css_var']) {
                $cv = self::css_value($f, $v);
                if ($cv !== '') {
                    $vars .= '--dlp-' . $id . '-' . str_replace('_', '-', $key) . ':' . $cv . ';';
                }
            }
            if ($f['js']) {
                $js[$key] = $f['type'] === 'checkbox' ? (bool) $v : $v;
            }
        }
        if ($vars !== '') {
            if (!wp_style_is($handle, 'registered')) {
                wp_register_style($handle, false, [], DLP_FE_VERSION);
                wp_enqueue_style($handle);
            }
            wp_add_inline_style($handle, ':root{' . $vars . '}');
        }
        if ($js && wp_script_is($handle, 'enqueued')) {
            wp_add_inline_script($handle, 'window.dlpFE=window.dlpFE||{};window.dlpFE[' . wp_json_encode($id) . ']=' . wp_json_encode($js) . ';', 'before');
        }
    }

    /** Barra de admin: muestra qué módulos están activos en esta página y permite apagarlos solo para tu vista. */
    public static function admin_bar($bar) {
        if (is_admin() || !current_user_can('manage_options')) {
            return;
        }
        $bar->add_node(['id' => 'dlp-fe', 'title' => 'DLP FE (' . count(self::$loaded) . ')', 'href' => admin_url('themes.php?page=dlp-frontend')]);
        foreach (self::$loaded as $id) {
            $bar->add_node([
                'id' => 'dlp-fe-' . $id, 'parent' => 'dlp-fe',
                'title' => esc_html($id) . ' — apagar en esta vista',
                'href'  => add_query_arg('dlp_fe_off', $id),
            ]);
        }
        $bar->add_node(['id' => 'dlp-fe-all', 'parent' => 'dlp-fe', 'title' => 'Ver sin ningún módulo', 'href' => add_query_arg('dlp_fe_off', 'all')]);
    }
}
