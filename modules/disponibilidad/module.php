<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Disponibilidad de productos.
 *  - Días: meta `_dlp_dias` (array de 0=domingo … 6=sábado). Vacío = todos los días.
 *  - Tiendas: meta `_dlp_tiendas_excluidas` (IDs de extra_store donde NO se vende).
 * Los días ocultan el producto de las listas y lo hacen no comprable. Las tiendas se validan en el
 * checkout, que es cuando se conoce la tienda (dlp-tiendas la resuelve ahí).
 */

const DLP_FE_DISP_DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

function dlp_fe_disp_cfg($k, $fallback = '') {
    return DLP_FE_Registry::setting('disponibilidad', $k, $fallback);
}

/** Productos con horario por días: [id => [días permitidos]]. Se cachea y se limpia al guardar. */
function dlp_fe_disp_programados() {
    $c = get_transient('dlp_fe_disp_prog');
    if (is_array($c)) {
        return $c;
    }
    global $wpdb;
    $rows = $wpdb->get_results("SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_dlp_dias'");
    $out = [];
    foreach ($rows as $r) {
        $d = maybe_unserialize($r->meta_value);
        if (is_array($d) && $d && count($d) < 7) {
            $out[(int) $r->post_id] = array_map('intval', $d);
        }
    }
    set_transient('dlp_fe_disp_prog', $out, 12 * HOUR_IN_SECONDS);
    return $out;
}

function dlp_fe_disp_hoy() {
    return (int) wp_date('w');
}

/** ¿Se vende hoy? */
function dlp_fe_disp_dia_ok($product_id) {
    $p = dlp_fe_disp_programados();
    return !isset($p[$product_id]) || in_array(dlp_fe_disp_hoy(), $p[$product_id], true);
}

function dlp_fe_disp_dias_txt($product_id) {
    $p = dlp_fe_disp_programados();
    $d = $p[$product_id] ?? [];
    sort($d);
    return implode(', ', array_map(function ($i) {
        return DLP_FE_DISP_DIAS[$i];
    }, $d));
}

/** IDs que hoy no se venden por día. */
function dlp_fe_disp_ocultos_hoy() {
    $hoy = dlp_fe_disp_hoy();
    return array_keys(array_filter(dlp_fe_disp_programados(), function ($d) use ($hoy) {
        return !in_array($hoy, $d, true);
    }));
}

function dlp_fe_disp_excluidas($product_id) {
    $e = get_post_meta($product_id, '_dlp_tiendas_excluidas', true);
    return is_array($e) ? array_map('intval', $e) : [];
}

function dlp_fe_disp_tiendas() {
    return get_posts(['post_type' => 'extra_store', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
}

/* ---------- Tienda: días ---------- */

// Fuera de las listas (tienda, categorías, Divi, shortcodes) los días en que no se vende.
add_action('pre_get_posts', function ($q) {
    if ((is_admin() && !wp_doing_ajax()) || !dlp_fe_disp_programados()) {
        return;
    }
    $pt = $q->get('post_type');
    if ($pt !== 'product' && !(is_array($pt) && in_array('product', $pt, true))) {
        return;
    }
    $ids = dlp_fe_disp_ocultos_hoy();
    if ($ids) {
        $q->set('post__not_in', array_unique(array_merge((array) $q->get('post__not_in'), $ids)));
    }
}, 20);

add_filter('woocommerce_is_purchasable', function ($ok, $product) {
    $id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
    return $ok && dlp_fe_disp_dia_ok($id);
}, 20, 2);

add_filter('woocommerce_product_is_visible', function ($v, $id) {
    return $v && dlp_fe_disp_dia_ok((int) $id);
}, 20, 2);

add_action('woocommerce_single_product_summary', function () {
    global $product;
    if ($product && !dlp_fe_disp_dia_ok($product->get_id())) {
        echo '<p class="dlp-disp-aviso" style="margin:12px 0;font-weight:600">' . esc_html(str_replace('{dias}', dlp_fe_disp_dias_txt($product->get_id()), (string) dlp_fe_disp_cfg('msg_dia'))) . '</p>';
    }
}, 25);

/* ---------- Checkout: tiendas ---------- */

add_action('woocommerce_after_checkout_validation', function ($data, $errors) {
    if (!function_exists('dlp_tiendas_resolve_checkout') || !WC()->cart) {
        return;
    }
    $r = dlp_tiendas_resolve_checkout();
    if (is_wp_error($r) || empty($r['store'])) {
        return; // dlp-tiendas ya reporta ese error
    }
    $store = (int) $r['store'];
    foreach (WC()->cart->get_cart() as $item) {
        $pid = (int) ($item['product_id'] ?? 0);
        if ($pid && in_array($store, dlp_fe_disp_excluidas($pid), true)) {
            $errors->add('dlp_prod_tienda', str_replace(['{producto}', '{tienda}'], [get_the_title($pid), get_the_title($store)], (string) dlp_fe_disp_cfg('msg_tienda')));
        }
    }
}, 25, 2);

/* ---------- Administración: caja en el producto ---------- */

add_action('add_meta_boxes_product', function () {
    add_meta_box('dlp-disp', 'Disponibilidad (DLP)', 'dlp_fe_disp_metabox', 'product', 'side', 'default');
});

function dlp_fe_disp_metabox($post) {
    $dias = get_post_meta($post->ID, '_dlp_dias', true);
    $dias = is_array($dias) && $dias ? array_map('intval', $dias) : range(0, 6);
    $exc  = dlp_fe_disp_excluidas($post->ID);
    wp_nonce_field('dlp_disp', 'dlp_disp_nonce');
    $orden = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 0 => 'Dom'];
    echo '<div class="dlpd dlpd-prod">';
    echo '<div class="dlpd-sec"><div class="dlpd-h"><strong>Días en que se vende</strong></div><div class="dlpd-quick">'
        . '<button type="button" data-set="all">Todos</button><button type="button" data-set="week">Entre semana</button><button type="button" data-set="weekend">Vie–Dom</button></div>'
        . '<div class="dlpd-pills dlpd-days">';
    foreach ($orden as $i => $n) {
        echo '<label class="dlpd-pill"><input type="checkbox" name="dlp_dias[]" value="' . $i . '" ' . checked(in_array($i, $dias, true), true, false) . '><span>' . esc_html($n) . '</span></label>';
    }
    echo '</div></div>';
    echo '<div class="dlpd-sec"><div class="dlpd-h"><strong>Tiendas donde NO se vende</strong><button type="button" class="dlpd-link" data-set="stores-none">Ninguna</button></div>'
        . '<input type="search" class="dlpd-find" placeholder="Buscar tienda…"><div class="dlpd-box"><div class="dlpd-pills dlpd-stores">';
    foreach (dlp_fe_disp_tiendas() as $s) {
        echo '<label class="dlpd-pill"><input type="checkbox" name="dlp_excl[]" value="' . (int) $s->ID . '" data-n="' . esc_attr($s->post_title) . '" ' . checked(in_array($s->ID, $exc, true), true, false) . '><span>' . esc_html($s->post_title) . '</span></label>';
    }
    echo '</div></div></div><div class="dlpd-sum"></div></div>';
}

add_action('save_post_product', function ($id) {
    if (!isset($_POST['dlp_disp_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dlp_disp_nonce'])), 'dlp_disp') || !current_user_can('edit_post', $id)) {
        return;
    }
    $dias = isset($_POST['dlp_dias']) ? array_values(array_unique(array_filter(array_map('intval', (array) $_POST['dlp_dias']), function ($d) {
        return $d >= 0 && $d <= 6;
    }))) : [];
    if (count($dias) >= 7 || !$dias) {
        delete_post_meta($id, '_dlp_dias');
    } else {
        update_post_meta($id, '_dlp_dias', $dias);
    }
    $exc = isset($_POST['dlp_excl']) ? array_values(array_unique(array_map('absint', (array) $_POST['dlp_excl']))) : [];
    $exc ? update_post_meta($id, '_dlp_tiendas_excluidas', $exc) : delete_post_meta($id, '_dlp_tiendas_excluidas');
    delete_transient('dlp_fe_disp_prog');
});

add_filter('manage_edit-product_columns', function ($c) {
    $c['dlp_disp'] = 'Disponibilidad';
    return $c;
});
add_action('manage_product_posts_custom_column', function ($col, $id) {
    if ($col !== 'dlp_disp') {
        return;
    }
    $p = dlp_fe_disp_programados();
    $partes = [];
    if (isset($p[$id])) {
        $partes[] = 'Solo ' . esc_html(dlp_fe_disp_dias_txt($id));
    }
    $exc = dlp_fe_disp_excluidas($id);
    if ($exc) {
        $partes[] = 'Sin: ' . esc_html(implode(', ', array_map('get_the_title', $exc)));
    }
    echo $partes ? implode('<br>', $partes) : '<span style="color:#a7aaad">Todos los días y tiendas</span>';
}, 10, 2);

/* ---------- Administración: por tienda (pestaña de DLP Config) ---------- */

add_action('admin_post_dlp_fe_disp', function () {
    if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
    }
    check_admin_referer('dlp_fe_disp');
    $store = isset($_POST['tienda']) ? absint($_POST['tienda']) : 0;
    if ($store && get_post_type($store) === 'extra_store') {
        $todos = isset($_POST['todos']) ? array_map('absint', (array) $_POST['todos']) : [];
        $on    = isset($_POST['on']) ? array_map('absint', (array) $_POST['on']) : [];
        foreach ($todos as $pid) {
            $e = dlp_fe_disp_excluidas($pid);
            $e = in_array($pid, $on, true) ? array_values(array_diff($e, [$store])) : array_values(array_unique(array_merge($e, [$store])));
            $e ? update_post_meta($pid, '_dlp_tiendas_excluidas', $e) : delete_post_meta($pid, '_dlp_tiendas_excluidas');
        }
    }
    wp_safe_redirect(DLP_FE_Admin::url('home', 'por-tienda') . '&tienda=' . $store . '&saved=1');
    exit;
});

function dlp_fe_disp_render_admin() {
    $tiendas = dlp_fe_disp_tiendas();
    $sel     = isset($_GET['tienda']) ? absint($_GET['tienda']) : ($tiendas ? $tiendas[0]->ID : 0);
    echo '<div class="dlpd dlpd-page"><div class="dlpfe-head"><div><h2>Disponibilidad por tienda</h2><p>Elige una tienda y desmarca los productos que NO vende. Se valida en el checkout, cuando ya se sabe qué tienda atiende el pedido. Para vender un producto solo ciertos días, abre el producto y usa la caja «Disponibilidad (DLP)».</p></div></div>';
    if (!$sel) {
        echo '<p><em>No hay tiendas.</em></p></div>';
        return;
    }
    $prods = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'suppress_filters' => true, 'no_found_rows' => true]);
    $grupos = [];
    foreach ($prods as $p) {
        $t = get_the_terms($p->ID, 'product_cat');
        $g = $t && !is_wp_error($t) ? $t[0]->name : 'Sin categoría';
        $grupos[$g][] = $p;
    }
    ksort($grupos);
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="dlpd-form">';
    wp_nonce_field('dlp_fe_disp');
    echo '<input type="hidden" name="action" value="dlp_fe_disp">';
    echo '<div class="dlpd-bar"><select name="tienda" aria-label="Tienda" onchange="if(!document.querySelector(\'.dlpd-dirty\').textContent||confirm(\'Hay cambios sin guardar. ¿Cambiar de tienda?\')){location.href=\'' . esc_url(admin_url('admin.php?page=dlp-config&tab=por-tienda&tienda=')) . '\'+this.value}">';
    foreach ($tiendas as $s) {
        echo '<option value="' . (int) $s->ID . '" ' . selected($sel, $s->ID, false) . '>' . esc_html($s->post_title) . '</option>';
    }
    echo '</select><input type="search" class="dlpd-q" placeholder="Buscar producto…"><span class="dlpd-count"></span></div>';
    foreach ($grupos as $g => $lista) {
        echo '<details open class="dlpd-cat"><summary>' . esc_html($g) . ' <em></em><span class="dlpd-acts"><button type="button" class="dlpd-link" data-cat="on">Vender todo</button><button type="button" class="dlpd-link" data-cat="off">Quitar todo</button></span></summary><div class="dlpd-grid">';
        foreach ($lista as $p) {
            $off = in_array($sel, dlp_fe_disp_excluidas($p->ID), true);
            echo '<label class="dlpd-item"><input type="hidden" name="todos[]" value="' . (int) $p->ID . '"><input type="checkbox" name="on[]" value="' . (int) $p->ID . '" ' . checked(!$off, true, false) . '><span>' . esc_html($p->post_title) . '</span></label>';
        }
        echo '</div></details>';
    }
    echo '<div class="dlpd-save"><button type="submit" class="button button-primary">Guardar tienda</button><span class="dlpd-dirty"></span></div></form></div>';
}

/* Estilos y scripts solo donde se usan */
add_action('admin_enqueue_scripts', function ($hook) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $esProducto = $screen && $screen->post_type === 'product' && in_array($hook, ['post.php', 'post-new.php'], true);
    $esPagina = isset($_GET['page'], $_GET['tab']) && $_GET['page'] === 'dlp-config' && $_GET['tab'] === 'por-tienda';
    if (!$esProducto && !$esPagina) {
        return;
    }
    if ($esPagina) {
        wp_enqueue_style('dlpfe-panel', DLP_FE_URL . 'admin/panel.css', [], filemtime(DLP_FE_DIR . 'admin/panel.css'));
    }
    wp_enqueue_style('dlpfe-disp', DLP_FE_URL . 'admin/disp.css', [], filemtime(DLP_FE_DIR . 'admin/disp.css'));
    wp_enqueue_script('dlpfe-disp', DLP_FE_URL . 'admin/disp.js', [], filemtime(DLP_FE_DIR . 'admin/disp.js'), true);
});
