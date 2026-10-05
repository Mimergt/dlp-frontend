<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Descubre los módulos (carpetas en /modules con module.json) y guarda su estado.
 * Opción: dlp_fe_modules = [ id => [ 'enabled' => 0|1, 'where' => 'checkout,cart' ] ]
 */
class DLP_FE_Registry {
    const OPTION = 'dlp_fe_modules';

    private static $modules = null;

    public static function all() {
        if (self::$modules !== null) {
            return self::$modules;
        }
        self::$modules = [];
        $state = get_option(self::OPTION, []);
        foreach (glob(DLP_FE_DIR . 'modules/*/module.json') ?: [] as $file) {
            $id   = basename(dirname($file));
            $meta = json_decode((string) file_get_contents($file), true);
            if (!is_array($meta)) {
                continue; // module.json roto: se ignora ese módulo, el resto sigue.
            }
            $saved   = isset($state[$id]) && is_array($state[$id]) ? $state[$id] : [];
            $where   = isset($saved['where']) && $saved['where'] !== ''
                ? array_filter(array_map('trim', explode(',', $saved['where'])))
                : (array) ($meta['where'] ?? ['all']);
            self::$modules[$id] = [
                'id'          => $id,
                'dir'         => dirname($file),
                'label'       => $meta['label'] ?? $id,
                'description' => $meta['description'] ?? '',
                'css'         => $meta['css'] ?? '',
                'js'          => $meta['js'] ?? '',
                'php'         => $meta['php'] ?? '',
                'boot'        => !empty($meta['boot']),
                'where'       => $where,
                'where_raw'   => $saved['where'] ?? '',
                'where_def'   => implode(',', (array) ($meta['where'] ?? ['all'])),
                'enabled'     => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : (bool) ($meta['default'] ?? true),
            ];
        }
        ksort(self::$modules);
        return self::$modules;
    }

    /** ¿Aplica el módulo a la página actual? Claves: all, home, shop, product, cart, checkout, account, category, page:slug, post_type:x */
    public static function matches(array $where) {
        foreach ($where as $w) {
            switch (true) {
                case $w === 'all':      return true;
                case $w === 'home':     if (is_front_page()) return true; break;
                case $w === 'shop':     if (function_exists('is_shop') && is_shop()) return true; break;
                case $w === 'product':  if (function_exists('is_product') && is_product()) return true; break;
                case $w === 'cart':     if (function_exists('is_cart') && is_cart()) return true; break;
                case $w === 'checkout': if (function_exists('is_checkout') && is_checkout()) return true; break;
                case $w === 'account':  if (function_exists('is_account_page') && is_account_page()) return true; break;
                case $w === 'category': if (function_exists('is_product_category') && is_product_category()) return true; break;
                case strpos($w, 'page:') === 0:      if (is_page(substr($w, 5))) return true; break;
                case strpos($w, 'post_type:') === 0: if (is_singular(substr($w, 10))) return true; break;
            }
        }
        return false;
    }
}
