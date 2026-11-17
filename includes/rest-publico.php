<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rutas REST públicas: un X-WP-Nonce vencido o inválido no debe tumbarlas.
 *
 * WordPress (rest_cookie_check_errors) responde 403 «rest_cookie_invalid_nonce» a CUALQUIER ruta si la petición trae un
 * X-WP-Nonce que no valida, aunque la ruta sea pública (incluso sin cookie). Pasa con páginas abiertas mucho rato (el nonce
 * se emitió para una sesión que ya cerró o venció), webviews que guardan el HTML o clientes que reutilizan un nonce viejo.
 * Para las rutas de esta lista el error se ignora y la petición sigue como anónima (el permission_callback de cada ruta decide).
 * Las rutas con sesión (dlp-paneles y el resto) NO están: siguen respondiendo 403 con un nonce inválido.
 *
 * Filtro: dlp_fe_rest_rutas_publicas (una ruta por elemento; con «*» al final = prefijo).
 */
function dlp_fe_rest_rutas_publicas() {
    return (array) apply_filters('dlp_fe_rest_rutas_publicas', [
        '/dlp/v1',
        '/dlp/v1/sitio/estado',
        '/dlp/v1/ubicacion/*',   // enlace/estado/punto: cada una valida su propio secreto o es pública
        '/dlp/v1/cobertura',
        '/dlp/v1/pedido/*',      // pickup/armar/crear: secreto x-dlp-secret
        '/auth/v1/*',            // registro/ingreso de la app
    ]);
}

function dlp_fe_rest_es_publica($route) {
    $route = untrailingslashit((string) $route);
    foreach (dlp_fe_rest_rutas_publicas() as $r) {
        $r = untrailingslashit((string) $r);
        if (substr($r, -2) === '/*') {
            $pre = substr($r, 0, -2);
            if ($route === $pre || strpos($route, $pre . '/') === 0) {
                return true;
            }
        } elseif ($route === $r) {
            return true;
        }
    }
    return false;
}

// Prioridad 101: rest_cookie_check_errors corre en 100.
add_filter('rest_authentication_errors', function ($result) {
    if (!is_wp_error($result) || $result->get_error_code() !== 'rest_cookie_invalid_nonce') {
        return $result;
    }
    $route = isset($GLOBALS['wp']->query_vars['rest_route']) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';
    if ($route !== '' && dlp_fe_rest_es_publica($route)) {
        wp_set_current_user(0); // anónima: el nonce no validó, así que no se confía en la cookie
        return null;
    }
    return $result;
}, 101);
