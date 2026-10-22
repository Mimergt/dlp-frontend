<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Home nuevo. Reemplaza el contenido de la página de inicio (header y footer de Divi se conservan).
 * Las tarjetas de promociones usan el mismo marcado que las del menú (ul.products li.product) para que se vean igual y
 * abran la vista rápida en el lugar. Los más pedidos son filas y abren la vista rápida cambiando el hash (#producto-slug).
 */
function dlp_fe_hn_codigos() {
    return ['dlp_home', 'dlp_banner', 'dlp_promos', 'dlp_categorias', 'dlp_mas_pedidos', 'dlp_footer'];
}

/** ¿La página actual usa alguno de los códigos? (entonces se aplica el fondo blanco y se cargan los estilos). */
function dlp_fe_hn_activo() {
    if (is_admin() || !function_exists('wc_get_product') || !is_singular()) {
        return false;
    }
    $p = get_post();
    if (!$p) {
        return false;
    }
    foreach (dlp_fe_hn_codigos() as $c) {
        if (has_shortcode($p->post_content, $c)) {
            return true;
        }
    }
    return false;
}

add_filter('body_class', function ($c) {
    if (dlp_fe_hn_activo()) {
        $c[] = 'dlph-page';
    }
    return $c;
});

function dlp_fe_hn_url($u) {
    $u = trim((string) $u);
    if ($u === '') {
        return home_url('/');
    }
    return preg_match('#^https?://#i', $u) ? $u : home_url('/' . ltrim($u, '/'));
}

function dlp_fe_hn_tarjeta($p) {
    $img = get_the_post_thumbnail_url($p->get_id(), 'woocommerce_single');
    ob_start();
    ?>
    <li class="product type-product post-<?php echo (int) $p->get_id(); ?> status-publish">
        <a href="<?php echo esc_url(get_permalink($p->get_id())); ?>" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
            <span class="et_shop_image"><?php if ($img) : ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($p->get_name()); ?>" loading="eager" decoding="async"><?php endif; ?><span class="et_overlay"></span></span>
            <h2 class="woocommerce-loop-product__title"><?php echo esc_html($p->get_name()); ?></h2>
            <span class="price"><?php echo wp_kses_post($p->get_price_html()); ?></span>
        </a>
    </li>
    <?php
    return ob_get_clean();
}

function dlp_fe_hn_slider($html, $n, $extra = '') {
    ob_start();
    ?>
    <div class="dlph-slider<?php echo $extra ? ' ' . esc_attr($extra) : ''; ?>">
        <?php echo $html; // phpcs:ignore ?>
        <div class="dlph-ind"><div class="dlph-dots"><?php echo str_repeat('<i></i>', max(1, (int) $n)); // phpcs:ignore ?></div><div class="dlph-arr"><button type="button" class="dlph-prev" aria-label="Anterior">‹</button><button type="button" class="dlph-next" aria-label="Siguiente">›</button></div></div>
    </div>
    <?php
    return ob_get_clean();
}

function dlp_fe_hn_o($k) {
    return DLP_FE_Registry::setting('home-nuevo', $k);
}

function dlp_fe_hn_envolver($html, $clase = '') {
    return $html === '' ? '' : '<div class="dlph' . ($clase ? ' ' . esc_attr($clase) : '') . '">' . $html . '</div>';
}

function dlp_fe_hn_banner() {
    $banners = '';
    $nb      = 0;
    for ($i = 1; $i <= 4; $i++) {
        $img = trim((string) dlp_fe_hn_o('banner' . $i . '_img'));
        if ($img === '') {
            continue;
        }
        $nb++;
        $mov = trim((string) dlp_fe_hn_o('banner' . $i . '_img_movil'));
        $banners .= '<a class="dlph-slide' . ($nb === 1 ? ' on' : '') . ($mov !== '' ? ' has-m' : '') . '" href="' . esc_url(dlp_fe_hn_url(dlp_fe_hn_o('banner' . $i . '_url'))) . '"><picture>'
            . ($mov !== '' ? '<source media="(max-width: 767px)" srcset="' . esc_url($mov) . '">' : '')
            . '<img class="dlph-bimg" src="' . esc_url($img) . '" alt="" ' . ($nb > 1 ? 'loading="lazy"' : 'fetchpriority="high"') . '></picture></a>';
    }
    if (!$nb) {
        return '';
    }
    return dlp_fe_hn_envolver('<section class="dlph-bn">' . dlp_fe_hn_slider('<div class="dlph-fade">' . $banners . '</div>', $nb, 'is-banner is-fade') . '</section>', 'dlph--banner');
}

function dlp_fe_hn_promos($a = []) {
    $slug = sanitize_title(!empty($a['categoria']) ? $a['categoria'] : dlp_fe_hn_o('promos_cat'));
    if ($slug === '') {
        return '';
    }
    $max = !empty($a['max']) ? (int) $a['max'] : (int) dlp_fe_hn_o('promos_max');
    $ids = get_posts([
        'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => max(1, $max),
        'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => $slug]],
        'orderby' => 'menu_order title', 'order' => 'ASC',
    ]);
    $cards = '';
    foreach ($ids as $id) {
        $p = wc_get_product($id);
        if ($p && $p->is_visible()) {
            $cards .= dlp_fe_hn_tarjeta($p);
        }
    }
    if ($cards === '') {
        return '';
    }
    $rotulo = isset($a['rotulo']) ? $a['rotulo'] : dlp_fe_hn_o('rotulo_promos');
    $titulo = isset($a['titulo']) ? $a['titulo'] : dlp_fe_hn_o('titulo_promos');
    $h      = '<section class="dlph-sec"><div class="dlph-eb">' . esc_html($rotulo) . '</div><div class="dlph-sh"><h2 class="dlph-h">' . esc_html($titulo) . '</h2>';
    $h     .= '<a class="dlph-pill" href="' . esc_url(dlp_fe_hn_url(dlp_fe_hn_o('url_menu')) . '#categoria-' . $slug) . '">' . esc_html(dlp_fe_hn_o('texto_ver_todas')) . '</a></div>';
    $h     .= dlp_fe_hn_slider('<div class="dlph-track dlph-cards"><ul class="products columns-4">' . $cards . '</ul></div>', count($ids), 'is-cards') . '</section>';
    return dlp_fe_hn_envolver($h);
}

function dlp_fe_hn_categorias($a = []) {
    $lista = isset($a['lista']) ? str_replace('/', "\n", $a['lista']) : (string) dlp_fe_hn_o('categorias');
    $tiles = '';
    $n     = 0;
    foreach (preg_split('/\r?\n/', $lista) as $linea) {
        $partes = array_map('trim', explode('|', $linea));
        if (empty($partes[0])) {
            continue;
        }
        $term = get_term_by('slug', sanitize_title($partes[0]), 'product_cat');
        if (!$term || is_wp_error($term)) {
            continue;
        }
        $titulo = !empty($partes[1]) ? $partes[1] : $term->name;
        $img    = !empty($partes[2]) ? $partes[2] : '';
        if ($img === '') {
            $tid = (int) get_term_meta($term->term_id, 'thumbnail_id', true);
            $img = $tid ? wp_get_attachment_image_url($tid, 'large') : '';
        }
        $n++;
        $tiles .= '<a class="dlph-ct" href="' . esc_url(dlp_fe_hn_url(dlp_fe_hn_o('url_menu')) . '#categoria-' . $term->slug) . '">' . ($img ? '<img src="' . esc_url($img) . '" alt="" loading="eager" decoding="async">' : '') . '<b class="dlph-h">' . esc_html($titulo) . '</b></a>';
    }
    if ($tiles === '') {
        return '';
    }
    // Escritorio: si la última fila de 4 queda incompleta, un solo cuadro de «menú completo» ocupa el hueco
    if ($n % 4 !== 0) {
        $tiles .= '<a class="dlph-ct dlph-ct--menu" href="' . esc_url(dlp_fe_hn_url(dlp_fe_hn_o('url_menu'))) . '"><svg class="dlph-ct-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 10.5a7.5 5.5 0 0 1 15 0z"/><path d="M3.5 13.5h17"/><path d="M4.5 16.5h15a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3z"/></svg><span class="dlph-ct-row"><b class="dlph-h">' . esc_html(dlp_fe_hn_o('texto_menu')) . '</b><i aria-hidden="true">→</i></span></a>';
    }
    $rotulo = isset($a['rotulo']) ? $a['rotulo'] : dlp_fe_hn_o('rotulo_cats');
    $titulo = isset($a['titulo']) ? $a['titulo'] : dlp_fe_hn_o('titulo_cats');
    return dlp_fe_hn_envolver('<section class="dlph-sec"><div class="dlph-eb">' . esc_html($rotulo) . '</div><div class="dlph-sh"><h2 class="dlph-h">' . esc_html($titulo) . '</h2></div>' . dlp_fe_hn_slider('<div class="dlph-track dlph-cats">' . $tiles . '</div>', $n, 'is-cats') . '</section>');
}

function dlp_fe_hn_mas_pedidos($a = []) {
    $ids   = isset($a['ids']) ? $a['ids'] : (string) dlp_fe_hn_o('top');
    $filas = '';
    foreach (array_filter(array_map('absint', explode(',', $ids))) as $id) {
        $p = wc_get_product($id);
        if (!$p || !$p->is_visible()) {
            continue;
        }
        $img  = get_the_post_thumbnail_url($id, 'woocommerce_single');
        $desc = wp_trim_words(wp_strip_all_tags($p->get_short_description() ?: $p->get_description()), 14, '…');
        $filas .= '<a class="dlph-li" href="' . esc_url(get_permalink($id)) . '" data-slug="' . esc_attr($p->get_slug()) . '">' . ($img ? '<img src="' . esc_url($img) . '" alt="" loading="eager" decoding="async">' : '') . '<span class="dlph-li-t"><b class="dlph-h">' . esc_html($p->get_name()) . '</b>' . ($desc ? '<em>' . esc_html($desc) . '</em>' : '') . '<strong>' . wp_kses_post($p->get_price_html()) . '</strong></span><u aria-hidden="true">+</u></a>';
    }
    if ($filas === '') {
        return '';
    }
    $rotulo = isset($a['rotulo']) ? $a['rotulo'] : dlp_fe_hn_o('rotulo_top');
    $titulo = isset($a['titulo']) ? $a['titulo'] : dlp_fe_hn_o('titulo_top');
    $h      = '<section class="dlph-sec"><div class="dlph-eb">' . esc_html($rotulo) . '</div><div class="dlph-sh"><h2 class="dlph-h">' . esc_html($titulo) . '</h2></div><div class="dlph-list">' . $filas . '</div>';
    $h     .= '</section>';
    return dlp_fe_hn_envolver($h);
}

function dlp_fe_hn_footer($a = []) {
    $fotos = '';
    $n     = 0;
    for ($i = 1; $i <= 4; $i++) {
        $img = trim((string) dlp_fe_hn_o('footer_img' . $i));
        if ($img === '') {
            continue;
        }
        $fotos .= '<div class="dlph-fs' . ($n === 0 ? ' on' : '') . '" style="background-image:url(' . esc_url($img) . ')"></div>';
        $n++;
    }
    $titulo = isset($a['titulo']) ? $a['titulo'] : dlp_fe_hn_o('footer_titulo');
    $boton  = isset($a['boton']) ? $a['boton'] : dlp_fe_hn_o('footer_boton');
    $tel    = isset($a['tel']) ? $a['tel'] : dlp_fe_hn_o('footer_tel');
    $h      = '<section class="dlph-ft"><div class="dlph-fslides" data-n="' . (int) $n . '">' . $fotos . '</div><div class="dlph-fshade"></div><div class="dlph-fbody">';
    if ($titulo !== '') {
        $h .= '<h2 class="dlph-h">' . esc_html($titulo) . '</h2>';
    }
    if ($boton !== '') {
        $h .= '<a class="dlph-fbtn" href="' . esc_url(dlp_fe_hn_url(dlp_fe_hn_o('footer_url'))) . '">' . esc_html($boton) . '</a>';
    }
    if ($tel !== '') {
        $h .= '<p class="dlph-ftel">Llámanos <a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $tel)) . '">' . esc_html($tel) . '</a></p>';
    }
    $h .= '</div></section>';
    return dlp_fe_hn_envolver($h, 'dlph--footer');
}

// Códigos para pegar en Divi (módulo Texto o Código), uno por sección
add_shortcode('dlp_banner', function () { return dlp_fe_hn_banner(); });
add_shortcode('dlp_promos', function ($a) { return dlp_fe_hn_promos((array) $a); });
add_shortcode('dlp_categorias', function ($a) { return dlp_fe_hn_categorias((array) $a); });
add_shortcode('dlp_mas_pedidos', function ($a) { return dlp_fe_hn_mas_pedidos((array) $a); });
add_shortcode('dlp_footer', function ($a) { return dlp_fe_hn_footer((array) $a); });
add_shortcode('dlp_home', function () { return dlp_fe_hn_banner() . dlp_fe_hn_promos() . dlp_fe_hn_categorias() . dlp_fe_hn_mas_pedidos() . dlp_fe_hn_footer(); });

// Tipografías propias (si se pegó la URL del archivo en los ajustes)
add_action('wp_head', function () {
    if (!dlp_fe_hn_activo()) {
        return;
    }
    $css = '';
    foreach (['fuente_titulos' => 'DLP Titulos', 'fuente_texto' => 'DLP Texto'] as $k => $fam) {
        $u = trim((string) DLP_FE_Registry::setting('home-nuevo', $k));
        if ($u !== '') {
            $css .= '@font-face{font-family:"' . $fam . '";font-weight:100 900;font-display:swap;src:url("' . esc_url($u) . '")}';
        }
    }
    if ($css !== '') {
        echo '<style id="dlph-fonts">' . $css . '</style>' . "\n"; // phpcs:ignore
    }
}, 8);

// Los banners se sirven con su tamaño original: el CDN de imágenes de Jetpack los reducía a 1080 px de ancho.
foreach (['jetpack_photon_skip_image', 'jetpack_image_cdn_skip_image'] as $hook) {
    add_filter($hook, function ($skip, $src = '', $tag = '') {
        return (is_string($tag) && strpos($tag, 'dlph-bimg') !== false) ? true : $skip;
    }, 10, 3);
}
