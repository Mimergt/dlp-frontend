<?php
/**
 * Feedback del cliente (2026-10-26), general para todos los productos (publicados y borradores): en textos y descripciones
 *   «papa/papas» sin más  → «papa frita/papas fritas»     (palitos de papa no se toca)
 *   «Frezka/Frezkas»      → «Frezka natural/Frezkas naturales» (no si ya dice «natural», si va seguida del sabor —Tamarindo, Jamaica…— ni «Frezka Té frío»)
 * Campos: título, descripción corta, descripción larga y, en los extras, nombre, descripción y etiquetas de opciones. Solo toca el texto
 * (no etiquetas HTML ni direcciones). Copia previa por producto en _dlp_textos_backup_papasfrezka. Idempotente.
 * Uso: wp eval-file papas-frezkas-textos.php [dry]
 */
$dry = in_array('dry', (array) ($args ?? []), true);

$fix = function ($s) {
    if (!is_string($s) || $s === '') { return $s; }
    $partes = preg_split('/(<[^>]*>|\[[^\]]*\])/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE); // etiquetas HTML y [shortcodes] quedan intactos
    foreach ($partes as $i => $t) {
        if ($t === '' || $t[0] === '<' || $t[0] === '[') { continue; }
        $t = preg_replace_callback('/(?<![\w\-\/.=#@])(?<!palitos de )(papas?)(?![\w\-]|\s+fritas?\b)/iu', function ($m) {
            $w = $m[1];
            return $w . (strtolower($w) === 'papa' ? ' frita' : ' fritas');
        }, $t);
        $t = preg_replace_callback('/(?<![\w\-\/.=#@])(Frezkas?)(?![\w\-])(?!\s+natural)(?!\s+(?:Tamarindo|Rosa|Jamaica|Sand[ií]a|Lim[oó]n|T[eé]\b))/iu', function ($m) {
            return $m[1] . (strtolower(substr($m[1], -1)) === 's' ? ' naturales' : ' natural');
        }, $t);
        $partes[$i] = $t;
    }
    return implode('', $partes);
};

$q = new WP_Query(['post_type' => 'product', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'posts_per_page' => -1, 'fields' => 'ids']);
$cambios = 0; $prods = 0;
foreach ($q->posts as $id) {
    $p = get_post($id);
    $n = ['post_title' => $fix($p->post_title), 'post_excerpt' => $fix($p->post_excerpt), 'post_content' => $fix($p->post_content)];
    $cambiado = ($n['post_title'] !== $p->post_title || $n['post_excerpt'] !== $p->post_excerpt || $n['post_content'] !== $p->post_content);
    $a = get_post_meta($id, '_product_addons', true);
    $na = $a;
    if (is_array($a)) {
        foreach ($a as $i => $x) {
            foreach (['name', 'description'] as $f) { if (isset($x[$f])) { $na[$i][$f] = $fix($x[$f]); } }
            foreach ((array) ($x['options'] ?? []) as $j => $o) { if (isset($o['label'])) { $na[$i]['options'][$j]['label'] = $fix($o['label']); } }
        }
    }
    $addCambiado = ($na !== $a);
    if (!$cambiado && !$addCambiado) { continue; }
    $prods++;
    foreach (['post_title', 'post_excerpt'] as $f) { if ($n[$f] !== $p->$f) { $cambios++; echo "#$id {$p->post_status} $f: " . html_entity_decode($p->$f) . "  →  " . html_entity_decode($n[$f]) . "\n"; } }
    if ($n['post_content'] !== $p->post_content) { $cambios++; echo "#$id {$p->post_status} contenido cambiado\n"; }
    if ($addCambiado) {
        foreach ($a as $i => $x) {
            foreach (['name', 'description'] as $f) { if (($na[$i][$f] ?? null) !== ($x[$f] ?? null)) { $cambios++; echo "#$id extra $f: {$x[$f]}  →  {$na[$i][$f]}\n"; } }
            foreach ((array) ($x['options'] ?? []) as $j => $o) { if ($na[$i]['options'][$j]['label'] !== $o['label']) { $cambios++; echo "#$id extra opción: {$o['label']}  →  {$na[$i]['options'][$j]['label']}\n"; } }
        }
    }
    if ($dry) { continue; }
    if (!get_post_meta($id, '_dlp_textos_backup_papasfrezka', true)) {
        update_post_meta($id, '_dlp_textos_backup_papasfrezka', wp_slash(wp_json_encode(['t' => $p->post_title, 'e' => $p->post_excerpt, 'c' => $p->post_content, 'a' => $a])));
    }
    if ($cambiado) { wp_update_post(wp_slash(['ID' => $id] + $n)); }
    if ($addCambiado) { update_post_meta($id, '_product_addons', $na); }
}
echo "\nproductos con cambios: $prods · textos cambiados: $cambios" . ($dry ? " (simulación, no se guardó nada)" : '') . "\n";
