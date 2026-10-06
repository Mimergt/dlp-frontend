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

/**
 * Reglas solo-oscuras (dark.css): se les antepone la condición dos veces, una para "automático" (teléfono en oscuro,
 * salvo que el cliente forzó claro) y otra para "forzado oscuro". Así dark.css se escribe sin repetir nada.
 */
function dlp_fe_tema_prefijar($css, $prefijo) {
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $out = '';
    $i   = 0;
    $n   = strlen($css);
    while ($i < $n) {
        $ab = strpos($css, '{', $i);
        if ($ab === false) {
            break;
        }
        $cab = trim(substr($css, $i, $ab - $i));
        // bloque hasta su llave de cierre
        $d = 1;
        $j = $ab + 1;
        while ($j < $n && $d > 0) {
            $c = $css[$j];
            $d += $c === '{' ? 1 : ($c === '}' ? -1 : 0);
            $j++;
        }
        $cuerpo = substr($css, $ab + 1, $j - $ab - 2);
        if ($cab !== '' && $cab[0] === '@') {
            $out .= $cab . '{' . dlp_fe_tema_prefijar($cuerpo, $prefijo) . '}';
        } elseif ($cab !== '') {
            $sels = [];
            $p    = 0;
            $cur  = '';
            foreach (str_split($cab) as $ch) {
                if ($ch === '(' || $ch === '[') {
                    $p++;
                } elseif ($ch === ')' || $ch === ']') {
                    $p--;
                }
                if ($ch === ',' && $p === 0) {
                    $sels[] = trim($cur);
                    $cur    = '';
                } else {
                    $cur .= $ch;
                }
            }
            $sels[] = trim($cur);
            $out .= implode(',', array_map(function ($s) use ($prefijo) { return $prefijo . ' ' . $s; }, array_filter($sels))) . '{' . trim($cuerpo) . '}';
        }
        $i = $j;
    }
    return $out;
}

add_action('wp_head', function () {
    $f = __DIR__ . '/dark.css';
    if (!is_readable($f)) {
        return;
    }
    $css = file_get_contents($f);
    echo '<style id="dlp-tema-dark">@media (prefers-color-scheme: dark){' . dlp_fe_tema_prefijar($css, 'html:not([data-dlp-tema="claro"])') . '}' . dlp_fe_tema_prefijar($css, 'html[data-dlp-tema="oscuro"]') . "</style>\n"; // phpcs:ignore
}, 99);
