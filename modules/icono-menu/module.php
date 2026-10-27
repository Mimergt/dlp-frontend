<?php
/**
 * Ícono del Menú: logo de línea con vapor (sin seleccionar) y mordida con migajas (en /menu/).
 * dlp_fe_im_svg() lo usan el menú inferior (PHP) y el botón del header de Divi (script.js, desde un <template>).
 * Estado: clase dlp-en-menu en <body> cuando se ve la página «Menú».
 */
if (!defined('ABSPATH')) { exit; }

const DLP_FE_IM_TOP = 'M500.57,243.88H36.39c-20.06,0-36.39-16.33-36.39-36.39v-17.71c0-47.3,25.52-94.52,70.02-129.55C104.94,32.75,167.71,0,268.48,0s163.54,32.75,198.46,60.23c44.5,35.03,70.02,82.24,70.02,129.55v17.71c0,20.07-16.33,36.39-36.39,36.39ZM268.48,15.73C144.24,15.73,15.73,80.83,15.73,189.78v17.71c0,11.4,9.27,20.67,20.66,20.67h464.18c11.4,0,20.67-9.27,20.67-20.67v-17.71c0-108.95-128.51-174.05-252.76-174.05Z';
const DLP_FE_IM_BOT = 'M506.37,353.5c9.02-8.67,14.64-20.84,14.64-34.31,0-26.24-21.35-47.59-47.58-47.59h-192.74c-17.86,0-32.39,14.53-32.39,32.4v80.21c0,12.4-10.09,22.48-22.48,22.48s-22.48-10.08-22.48-22.48v-74.4c0-21.07-17.14-38.21-38.21-38.21s-37.52,16.46-38.19,36.95l-.02.25c0,12.39-10.08,22.48-22.48,22.48s-22.47-10.09-22.47-22.48v-10.65c0-14.64-11.91-26.55-26.55-26.55-26.24,0-47.58,21.35-47.58,47.59,0,13.47,5.62,25.64,14.64,34.31-8.65,4.09-14.64,12.9-14.64,23.09,0,55.12,43.82,96.69,101.93,96.69h309.3c58.12,0,101.94-41.57,101.94-96.69,0-10.19-5.99-19-14.64-23.09ZM264.03,304c0-9.19,7.47-16.67,16.66-16.67h192.74c17.56,0,31.85,14.29,31.85,31.86s-14.29,31.86-31.85,31.86h-209.4v-47.05ZM55.42,287.33c5.97,0,10.82,4.85,10.82,10.82v10.65c0,21.06,17.14,38.2,38.2,38.2s37.53-16.47,38.19-37.19h.02c0-12.39,10.09-22.48,22.48-22.48s22.48,10.09,22.48,22.48v41.24H55.42c-17.56,0-31.86-14.3-31.86-31.86s14.3-31.86,31.86-31.86ZM419.07,457.56H109.77c-49.14,0-86.21-34.81-86.21-80.97,0-5.41,4.41-9.82,9.82-9.82h154.23v17.44c0,21.07,17.14,38.21,38.21,38.21s38.21-17.14,38.21-38.21v-17.44h231.43c5.42,0,9.82,4.41,9.82,9.82,0,46.16-37.06,80.97-86.21,80.97Z';

/** Bocado grande: tres círculos que recortan el contorno y dibujan un filo nuevo con muescas (el logo es de línea fina). */
function dlp_fe_im_svg() {
    static $n = 0;
    $id    = 'dlpim' . (++$n);
    $bite  = [[452, 34, 78], [530, 104, 72], [566, 196, 66]];
    $sw    = 15.7;
    $outer = function ($d) { return explode('ZM', $d)[0] . 'Z'; };
    $cut = $defs = $edge = '';
    foreach ($bite as $k => $c) {
        $cut .= '<circle cx="' . $c[0] . '" cy="' . $c[1] . '" r="' . $c[2] . '"/>';
        $others = '';
        foreach ($bite as $kk => $o) {
            if ($kk !== $k) { $others .= '<circle cx="' . $o[0] . '" cy="' . $o[1] . '" r="' . $o[2] . '" fill="#000"/>'; }
        }
        $defs .= '<mask id="' . $id . 's' . $k . '" maskUnits="userSpaceOnUse" x="-60" y="-140" width="700" height="700"><rect x="-60" y="-140" width="700" height="700" fill="#fff"/>' . $others . '</mask>';
        $edge .= '<circle cx="' . $c[0] . '" cy="' . $c[1] . '" r="' . ($c[2] + $sw / 2) . '" fill="none" stroke="currentColor" stroke-width="' . $sw . '" mask="url(#' . $id . 's' . $k . ')"/>';
    }
    return '<svg class="dlp-im" viewBox="-60 -140 660 700" fill="currentColor" aria-hidden="true" focusable="false">'
        . '<defs><mask id="' . $id . '" maskUnits="userSpaceOnUse" x="-60" y="-140" width="660" height="700"><rect x="-60" y="-140" width="660" height="700" fill="#fff"/><g class="bite" fill="#000">' . $cut . '</g></mask>'
        . '<clipPath id="' . $id . 'c"><path d="' . $outer(DLP_FE_IM_TOP) . '"/><path d="' . $outer(DLP_FE_IM_BOT) . '"/></clipPath>' . $defs . '</defs>'
        . '<g mask="url(#' . $id . ')"><path d="' . DLP_FE_IM_BOT . '"/><path d="' . DLP_FE_IM_TOP . '"/></g>'
        . '<g clip-path="url(#' . $id . 'c)"><g class="bite">' . $edge . '</g></g>'
        . '<g class="steam" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round"><path d="M170 -24c-26 -26 26 -50 0 -78"/><path d="M270 -34c-26 -26 26 -50 0 -78"/><path d="M370 -24c-26 -26 26 -50 0 -78"/></g>'
        . '<g class="crumbs" fill="currentColor"><circle cx="500" cy="150" r="9"/><circle cx="522" cy="200" r="7"/><circle cx="492" cy="246" r="8"/></g>'
        . '</svg>';
}

add_filter('body_class', function ($c) {
    $pg = get_page_by_path('menu');
    if ($pg && is_page($pg->ID)) { $c[] = 'dlp-en-menu'; }
    return $c;
});

/* Plantilla para el botón del header de Divi: script.js la clona dentro del botón que apunta a /menu/. */
add_action('wp_footer', function () {
    echo '<template id="dlp-im-tpl">' . dlp_fe_im_svg() . '</template>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}, 5);
