<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Home nuevo. Reemplaza el contenido de la página de inicio (header y footer de Divi se conservan).
 * Las tarjetas de promociones usan el mismo marcado que las del menú (ul.products li.product) para que se vean igual y
 * abran la vista rápida en el lugar. Los más pedidos son filas y abren la vista rápida cambiando el hash (#producto-slug).
 */
function dlp_fe_hn_activo() {
    return !is_admin() && is_front_page() && function_exists('wc_get_product');
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
            <span class="et_shop_image"><?php if ($img) : ?><img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($p->get_name()); ?>" loading="lazy"><?php endif; ?><span class="et_overlay"></span></span>
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

function dlp_fe_hn_render() {
    $o = function ($k) {
        return DLP_FE_Registry::setting('home-nuevo', $k);
    };
    ob_start();
    echo '<div class="dlph">';

    // 1. Banners
    $banners = '';
    $nb      = 0;
    for ($i = 1; $i <= 4; $i++) {
        $img = trim((string) $o('banner' . $i . '_img'));
        if ($img === '') {
            continue;
        }
        $nb++;
        $banners .= '<a class="dlph-slide" href="' . esc_url(dlp_fe_hn_url($o('banner' . $i . '_url'))) . '"><img src="' . esc_url($img) . '" alt="" ' . ($nb > 1 ? 'loading="lazy"' : 'fetchpriority="high"') . '></a>';
    }
    if ($nb) {
        echo '<section class="dlph-bn">' . dlp_fe_hn_slider('<div class="dlph-track">' . $banners . '</div>', $nb, 'is-banner') . '</section>'; // phpcs:ignore
    }

    // 2. Promociones
    $slug = sanitize_title($o('promos_cat'));
    if ($slug !== '') {
        $ids = get_posts([
            'post_type' => 'product', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => max(1, (int) $o('promos_max')),
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
        if ($cards !== '') {
            $term = get_term_by('slug', $slug, 'product_cat');
            echo '<section class="dlph-sec"><div class="dlph-eb">' . esc_html($o('rotulo_promos')) . '</div><div class="dlph-sh"><h2 class="dlph-h">' . esc_html($o('titulo_promos')) . '</h2>';
            if ($term && !is_wp_error($term)) {
                echo '<a class="dlph-pill" href="' . esc_url(dlp_fe_hn_url($o('url_menu')) . '#categoria-' . $slug) . '">' . esc_html($o('texto_ver_todas')) . '</a>';
            }
            echo '</div>' . dlp_fe_hn_slider('<div class="dlph-track dlph-cards"><ul class="products columns-4">' . $cards . '</ul></div>', count($ids), 'is-cards'); // phpcs:ignore
            echo '</section>';
        }
    }

    // 3. Categorías
    $tiles = '';
    foreach (preg_split('/\r?\n/', (string) $o('categorias')) as $linea) {
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
        $tiles .= '<a class="dlph-ct" href="' . esc_url(dlp_fe_hn_url($o('url_menu')) . '#categoria-' . $term->slug) . '">' . ($img ? '<img src="' . esc_url($img) . '" alt="" loading="lazy">' : '') . '<b class="dlph-h">' . esc_html($titulo) . '</b></a>';
    }
    if ($tiles !== '') {
        echo '<section class="dlph-sec"><div class="dlph-eb">' . esc_html($o('rotulo_cats')) . '</div><div class="dlph-sh"><h2 class="dlph-h">' . esc_html($o('titulo_cats')) . '</h2></div>';
        echo dlp_fe_hn_slider('<div class="dlph-track dlph-cats">' . $tiles . '</div>', substr_count($tiles, 'dlph-ct"'), 'is-cats') . '</section>'; // phpcs:ignore
    }

    // 4. Los más pedidos
    $filas = '';
    foreach (array_filter(array_map('absint', explode(',', (string) $o('top')))) as $id) {
        $p = wc_get_product($id);
        if (!$p || !$p->is_visible()) {
            continue;
        }
        $img  = get_the_post_thumbnail_url($id, 'woocommerce_single');
        $desc = wp_trim_words(wp_strip_all_tags($p->get_short_description() ?: $p->get_description()), 14, '…');
        $filas .= '<a class="dlph-li" href="' . esc_url(get_permalink($id)) . '" data-slug="' . esc_attr($p->get_slug()) . '">' . ($img ? '<img src="' . esc_url($img) . '" alt="" loading="lazy">' : '') . '<span class="dlph-li-t"><b class="dlph-h">' . esc_html($p->get_name()) . '</b>' . ($desc ? '<em>' . esc_html($desc) . '</em>' : '') . '<strong>' . wp_kses_post($p->get_price_html()) . '</strong></span><u aria-hidden="true">+</u></a>';
    }
    if ($filas !== '') {
        echo '<section class="dlph-sec"><div class="dlph-eb">' . esc_html($o('rotulo_top')) . '</div><div class="dlph-sh"><h2 class="dlph-h">' . esc_html($o('titulo_top')) . '</h2></div><div class="dlph-list">' . $filas . '</div>'; // phpcs:ignore
        echo '<a class="dlph-full" href="' . esc_url(dlp_fe_hn_url($o('url_menu'))) . '">' . esc_html($o('texto_menu')) . ' <span aria-hidden="true">→</span></a></section>';
    }

    echo '</div>';
    return ob_get_clean();
}

add_filter('the_content', function ($content) {
    if (!dlp_fe_hn_activo() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    return dlp_fe_hn_render();
}, 20);

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
