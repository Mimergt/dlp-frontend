<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modo claro/oscuro. El atributo data-dlp-tema en <html> (claro|oscuro) fuerza un modo; sin atributo manda el teléfono
 * (prefers-color-scheme). Se fija en <head> antes de pintar para que no parpadee.
 */
add_action('wp_head', function () {
    $modo = DLP_FE_Registry::setting('tema', 'modo');
    $modo = in_array($modo, ['claro', 'oscuro'], true) ? $modo : 'auto';
    $cs   = $modo === 'auto' ? 'light dark' : ($modo === 'oscuro' ? 'dark' : 'light');
    echo '<meta name="color-scheme" content="' . esc_attr($cs) . '">' . "\n";
    echo '<script>(function(){var d=document.documentElement,t=' . wp_json_encode($modo) . ';try{var s=localStorage.getItem("dlp_tema");if(s==="claro"||s==="oscuro"||s==="auto")t=s}catch(e){}if(t!=="auto")d.setAttribute("data-dlp-tema",t)})();</script>' . "\n";
}, 1);
