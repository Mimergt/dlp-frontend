<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/oauth-functions.php';

/**
 * CORS: antes el tema enviaba "Access-Control-Allow-Origin: *" en TODAS las peticiones.
 * Ahora solo en los endpoints que usa la app (registro por ajax y la ruta REST auth/v1).
 * Más endpoints: add_filter('dlp_fe_cors_ajax_actions', ...).
 */
add_action('init', function () {
    $actions = apply_filters('dlp_fe_cors_ajax_actions', ['custom_register_from_app']);
    $action  = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    $uri     = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
    $route   = isset($_GET['rest_route']) ? (string) wp_unslash($_GET['rest_route']) : '';
    $is_ajax = wp_doing_ajax() && in_array($action, $actions, true);
    $is_rest = strpos($uri, '/auth/v1/') !== false || strpos($route, '/auth/v1/') === 0;
    if (($is_ajax || $is_rest) && !headers_sent()) {
        header('Access-Control-Allow-Origin: *');
    }
});
