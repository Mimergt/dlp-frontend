<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Duración de la sesión recordada («Recordarme» o app móvil): 30 días (ajuste login-pagina → sesion_dias) solo para clientes.
 * Administradores, tiendas, supervisores y demás roles conservan los 14 días de WordPress.
 * Va en includes/ (y no en un módulo) porque el autologin de la app (modules/app-login) inicia sesión al cargarse, antes que los módulos de orden posterior.
 */
add_filter('auth_cookie_expiration', function ($length, $user_id = 0, $remember = false) {
    if (!$remember) {
        return $length;
    }
    $u = get_userdata((int) $user_id);
    if (!$u) {
        return $length;
    }
    $roles = (array) $u->roles;
    if (!$roles || array_diff($roles, ['customer', 'subscriber'])) {
        return $length;
    }
    $dias = (int) DLP_FE_Registry::setting('login-pagina', 'sesion_dias', 30);
    return $dias > 0 ? $dias * DAY_IN_SECONDS : $length;
}, 20, 3);
