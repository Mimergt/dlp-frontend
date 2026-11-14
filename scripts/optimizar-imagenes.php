<?php
/**
 * Crea versiones WebP livianas, al lado de los originales, de las imágenes del home (banners y cuadros de categorías).
 * Uso:  wp eval-file scripts/optimizar-imagenes.php [dry|aplicar]
 *  - No modifica ni borra los originales ni los ajustes: crea  nombre.webp  (y  nombre-m.webp  para banners en celular).
 *  - El módulo home-nuevo detecta esos archivos y los usa solo si existen (si no, sigue usando el original).
 *  - Solo conserva el WebP si es al menos 15 % más liviano que el original. Es idempotente.
 *  - Sirve igual en producción (lee las imágenes de los ajustes del módulo, sin IDs fijos).
 */
@ini_set('memory_limit', '512M');
$modo = $args[0] ?? 'dry';
$o    = get_option('dlp_fe_modules', []);
$s    = $o['home-nuevo']['settings'] ?? [];
$up   = wp_get_upload_dir();

$trabajos = []; // [url, ancho máx, sufijo]
for ($i = 1; $i <= 4; $i++) {
    if (!empty($s['banner' . $i . '_img'])) {
        $trabajos[] = [$s['banner' . $i . '_img'], 1600, ''];
        $trabajos[] = [$s['banner' . $i . '_img'], 800, '-m'];
    }
}
foreach (preg_split('/\R/', (string) ($s['categorias'] ?? '')) as $linea) {
    $p = array_map('trim', explode('|', $linea));
    if (!empty($p[2])) {
        $trabajos[] = [$p[2], 720, ''];
        $trabajos[] = [$p[2], 480, '-t']; // cuadros del home (se muestran a ~280 px de ancho)
    }
}

// Fotos de los productos que salen en el home (Promociones y Lo más pedido), en el tamaño que usan las tarjetas
$ids = array_filter(array_map('absint', explode(',', (string) ($s['top'] ?? ''))));
$cat = sanitize_title((string) ($s['promos_cat'] ?? ''));
if ($cat !== '') {
    $ids = array_merge($ids, get_posts(['post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => 40,
        'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $cat]]]));
}
foreach (array_unique($ids) as $pid) {
    $u = get_the_post_thumbnail_url($pid, 'woocommerce_single');
    if ($u) {
        $trabajos[] = [$u, 600, ''];
    }
}

$antes = $despues = 0;
foreach ($trabajos as [$url, $max, $suf]) {
    if (!preg_match('#/wp-content/uploads/(.+\.(jpe?g|png|webp))$#i', $url, $m)) {
        continue;
    }
    $orig = $up['basedir'] . '/' . $m[1];
    $dest = preg_replace('/\.(jpe?g|png|webp)$/i', $suf . '.webp', $orig);
    if ($dest === $orig) {
        continue; // un .webp sin sufijo ya es el original
    }
    if (!is_file($orig)) {
        echo "NO EXISTE  $orig\n";
        continue;
    }
    if (is_file($dest)) {
        echo "ya existe  " . basename($dest) . "\n";
        continue;
    }
    $tam = filesize($orig);
    if ($modo !== 'aplicar') {
        echo "crearía    " . basename($dest) . " (original " . round($tam / 1024) . " KB)\n";
        continue;
    }
    $img = preg_match('/png$/i', $orig) ? @imagecreatefrompng($orig) : (preg_match('/webp$/i', $orig) ? @imagecreatefromwebp($orig) : @imagecreatefromjpeg($orig));
    if (!$img) {
        echo "ERROR leyendo $orig\n";
        continue;
    }
    $w = imagesx($img);
    if ($w > $max) {
        $h   = (int) round(imagesy($img) * $max / $w);
        $red = imagecreatetruecolor($max, $h);
        imagealphablending($red, false);
        imagesavealpha($red, true);
        imagecopyresampled($red, $img, 0, 0, 0, 0, $max, $h, $w, imagesy($img));
        imagedestroy($img);
        $img = $red;
    }
    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    imagewebp($img, $dest, 80);
    imagedestroy($img);
    $nuevo = filesize($dest);
    if ($nuevo > $tam * 0.85 && $suf !== '-t') {
        unlink($dest);
        echo "descartado " . basename($dest) . " (" . round($nuevo / 1024) . " KB no mejora " . round($tam / 1024) . " KB)\n";
        continue;
    }
    $antes += $tam;
    $despues += $nuevo;
    echo "creado     " . basename($dest) . "  " . round($tam / 1024) . " KB -> " . round($nuevo / 1024) . " KB\n";
}
if ($modo === 'aplicar') {
    echo "Total: " . round($antes / 1024) . " KB -> " . round($despues / 1024) . " KB\n";
}
