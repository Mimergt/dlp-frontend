<?php
/**
 * Plugin Name: DLP Frontend
 * Description: Ajustes de front-end de Del Puente (CSS/JS por módulos que se pueden activar o desactivar por página).
 * Version: 0.18.1
 * Author: Mimer
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DLP_FE_VERSION', '0.18.1');
define('DLP_FE_FILE', __FILE__);
define('DLP_FE_DIR', plugin_dir_path(__FILE__));
define('DLP_FE_URL', plugin_dir_url(__FILE__));

// Actualizaciones automáticas desde GitHub (rama main).
// Para publicar una actualización: subir "Version:" arriba y hacer push a main.
require_once DLP_FE_DIR . 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$dlp_fe_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/Mimergt/dlp-frontend/',
    __FILE__,
    'dlp-frontend'
);
$dlp_fe_update_checker->setBranch('main');

// Interruptor de emergencia: define('DLP_FE_DISABLED', true); en wp-config.php apaga todo el plugin.
if (defined('DLP_FE_DISABLED') && DLP_FE_DISABLED) {
    return;
}

require_once DLP_FE_DIR . 'includes/class-registry.php';
require_once DLP_FE_DIR . 'includes/class-loader.php';

DLP_FE_Loader::init();

if (is_admin()) {
    require_once DLP_FE_DIR . 'includes/class-admin.php';
    DLP_FE_Admin::init();
}
