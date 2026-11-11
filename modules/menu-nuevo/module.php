<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Menú nuevo. Se pinta sobre la página con el slug del ajuste (menu-nuevo mientras se prueba; para cambiar el menú
 * viejo se renombra esa página y se pone «menu»). Reemplaza el contenido de la página: una barra de categorías fija,
 * todas las categorías en una sola página y tarjetas con el mismo marcado de siempre (ul.products li.product.post-ID
 * a.woocommerce-LoopProduct-link), así que la vista rápida (home-quickview) abre el producto sin cambios.
 * El HTML se guarda en una caché (transient) que se invalida al guardar productos, categorías o relaciones.
 */

function dlp_fe_mn_cfg($k) {
    return DLP_FE_Registry::setting('menu-nuevo', $k);
}

function dlp_fe_mn_is_page() {
    $slug = trim((string) dlp_fe_mn_cfg('slug'), '/');
    return $slug !== '' && !is_admin() && function_exists('is_page') && is_page($slug);
}

add_filter('body_class', function ($c) {
    if (dlp_fe_mn_is_page()) {
        $c[] = 'dlpm-page';
    }
    return $c;
});

// Mientras se prueba (slug distinto de «menu») la página no se indexa.
add_filter('wp_robots', function ($r) {
    if (dlp_fe_mn_is_page() && trim((string) dlp_fe_mn_cfg('slug'), '/') !== 'menu') {
        $r['noindex'] = true;
        $r['nofollow'] = true;
    }
    return $r;
});

/** [[slug, título, foto]] de las categorías del ajuste que existen. */
function dlp_fe_mn_categorias() {
    $out = [];
    foreach (preg_split('/\r?\n/', (string) dlp_fe_mn_cfg('categorias')) as $linea) {
        $p = array_map('trim', explode('|', $linea));
        if ($p[0] === '' || !($t = get_term_by('slug', $p[0], 'product_cat'))) {
            continue;
        }
        $foto = $p[2] ?? '';
        if ($foto === '') {
            $tid = (int) get_term_meta($t->term_id, 'thumbnail_id', true);
            $foto = $tid ? (string) wp_get_attachment_image_url($tid, 'thumbnail') : '';
        } elseif (!preg_match('#^https?://#i', $foto)) {
            $foto = home_url('/' . ltrim($foto, '/'));
        }
        $out[] = ['slug' => $t->slug, 'titulo' => ($p[1] ?? '') !== '' ? $p[1] : $t->name, 'foto' => $foto, 'id' => $t->term_id];
    }
    return $out;
}

function dlp_fe_mn_tarjeta($p, $eager) {
    $id   = $p->get_id();
    $img  = get_the_post_thumbnail($id, 'woocommerce_thumbnail', [
        'loading'  => $eager ? 'eager' : 'lazy',
        'decoding' => 'async',
        'sizes'    => '(max-width: 767px) 46vw, (max-width: 1100px) 30vw, 260px',
        'alt'      => $p->get_name(),
    ]);
    if (!$img) {
        $img = wc_placeholder_img('woocommerce_thumbnail');
    }
    $q = strtolower(remove_accents(html_entity_decode($p->get_name())));
    ob_start();
    ?>
    <li class="product type-product post-<?php echo (int) $id; ?> status-publish" data-q="<?php echo esc_attr($q); ?>">
        <a href="<?php echo esc_url(get_permalink($id)); ?>" class="woocommerce-LoopProduct-link woocommerce-loop-product__link">
            <span class="dlpm-img"><?php echo $img; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><i class="dlpm-plus" aria-hidden="true">+</i></span>
            <h3 class="woocommerce-loop-product__title"><?php echo esc_html($p->get_name()); ?></h3>
            <span class="price"><?php echo wp_kses_post($p->get_price_html()); ?></span>
        </a>
    </li>
    <?php
    return ob_get_clean();
}

function dlp_fe_mn_resaltes() {
    return ['fondo' => 'Fondo', 'linea' => 'Línea', 'escala' => 'Escala', 'tarjeta' => 'Tarjeta', 'contraste' => 'Contraste'];
}

function dlp_fe_mn_html() {
    $cats  = dlp_fe_mn_categorias();
    $res   = dlp_fe_mn_cfg('resalte');
    $res   = isset(dlp_fe_mn_resaltes()[$res]) ? $res : 'fondo';
    $prueba = trim((string) dlp_fe_mn_cfg('slug'), '/') !== 'menu';
    $key   = 'dlp_fe_mn_' . md5(DLP_FE_VERSION . '|' . get_option('dlp_fe_mn_ver', '0') . '|' . dlp_fe_mn_cfg('categorias') . '|' . dlp_fe_mn_cfg('buscar') . '|' . $res . '|' . (int) $prueba);
    $html  = get_transient($key);
    if (is_string($html) && $html !== '') {
        return $html;
    }
    $con = [];
    foreach ($cats as $c) {
        $prods = wc_get_products(['status' => 'publish', 'limit' => -1, 'category' => [$c['slug']], 'orderby' => 'menu_order', 'order' => 'ASC', 'visibility' => 'catalog']);
        if ($prods) {
            $c['prods'] = $prods;
            $con[] = $c;
        }
    }
    $img = function ($c, $w) {
        return $c['foto'] ? '<img src="' . esc_url($c['foto']) . '" alt="" width="' . $w . '" height="' . $w . '" loading="eager" decoding="async">' : '';
    };
    ob_start();
    ?>
    <div class="dlpm" id="dlpm" data-resalte="<?php echo esc_attr($res); ?>"<?php echo $prueba ? ' data-prueba="1"' : ''; ?>>
        <div class="dlpm-nav" id="dlpm-nav">
            <div class="dlpm-bar">
                <button type="button" class="dlpm-sbtn" aria-label="<?php echo esc_attr(dlp_fe_mn_cfg('buscar')); ?>" aria-expanded="false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </button>
                <div class="dlpm-chips" role="tablist" aria-label="Categorías">
                    <?php foreach ($con as $i => $c) : ?>
                        <a class="dlpm-chip<?php echo $i === 0 ? ' on' : ''; ?>" role="tab" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" href="<?php echo esc_url(add_query_arg('categoria', $c['slug'])); ?>" data-s="<?php echo esc_attr($c['slug']); ?>" data-t="<?php echo esc_attr($c['titulo']); ?>">
                            <?php echo $img($c, 64); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <span><?php echo esc_html($c['titulo']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dlpm-prog" aria-hidden="true"><i></i></div>
            <div class="dlpm-search" hidden>
                <label><span class="screen-reader-text"><?php echo esc_html(dlp_fe_mn_cfg('buscar')); ?></span>
                    <input type="search" class="dlpm-q" placeholder="<?php echo esc_attr(dlp_fe_mn_cfg('buscar')); ?>" autocomplete="off" enterkeyhint="search">
                </label>
                <button type="button" class="dlpm-qx" aria-label="Borrar búsqueda" hidden>&times;</button>
            </div>
        </div>
        <div class="dlpm-body">
            <?php $n = 0; foreach ($con as $i => $c) : ?>
                <section class="dlpm-sec" id="categoria-<?php echo esc_attr($c['slug']); ?>" data-s="<?php echo esc_attr($c['slug']); ?>" aria-label="<?php echo esc_attr($c['titulo']); ?>"<?php echo $i === 0 ? '' : ' hidden'; ?>>
                    <ul class="products dlpm-grid columns-4">
                        <?php foreach ($c['prods'] as $p) { echo dlp_fe_mn_tarjeta($p, $i === 0 && $n++ < 4); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </ul>
                </section>
            <?php endforeach; ?>
            <p class="dlpm-none" hidden>No encontramos productos con ese nombre.</p>
        </div>
        <?php if ($prueba) : ?>
            <div class="dlpm-prueba" hidden role="group" aria-label="Probar cómo se resalta la categoría actual">
                <b>Resalte</b>
                <?php foreach (dlp_fe_mn_resaltes() as $k => $l) : ?><button type="button" data-b="<?php echo esc_attr($k); ?>"><?php echo esc_html($l); ?></button><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
    $html = ob_get_clean();
    set_transient($key, $html, 12 * HOUR_IN_SECONDS);
    return $html;
}

add_filter('the_content', function ($content) {
    if (!dlp_fe_mn_is_page() || !in_the_loop() || !is_main_query() || !function_exists('wc_get_products')) {
        return $content;
    }
    return dlp_fe_mn_html();
}, 20);

/** Cualquier cambio en productos o categorías invalida el HTML guardado. */
function dlp_fe_mn_invalidar() {
    update_option('dlp_fe_mn_ver', (string) microtime(true), false);
}
add_action('save_post_product', 'dlp_fe_mn_invalidar');
add_action('woocommerce_update_product', 'dlp_fe_mn_invalidar');
add_action('trashed_post', 'dlp_fe_mn_invalidar');
add_action('untrashed_post', 'dlp_fe_mn_invalidar');
add_action('deleted_post', 'dlp_fe_mn_invalidar');
add_action('edited_product_cat', 'dlp_fe_mn_invalidar');
add_action('set_object_terms', function ($id, $terms, $tt, $taxonomy) {
    if ($taxonomy === 'product_cat') {
        dlp_fe_mn_invalidar();
    }
}, 10, 4);
