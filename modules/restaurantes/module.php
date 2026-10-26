<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Página Restaurantes. Se pinta sobre la página existente (por defecto /restaurantes/); el contenido de Divi
 * se conserva pero no se muestra. Los datos salen de dlp-tiendas (extra_store, horarios, punto en el mapa).
 * Metas propias de la tienda: _dlp_region (clave de dlp_fe_rs_regiones) y _dlp_ocultar_web.
 */

function dlp_fe_rs_cfg($k) {
    return DLP_FE_Registry::setting('restaurantes', $k);
}

function dlp_fe_rs_is_page() {
    $slug = trim((string) dlp_fe_rs_cfg('slug'), '/');
    return $slug !== '' && !is_admin() && function_exists('is_page') && is_page($slug);
}

function dlp_fe_rs_regiones() {
    return [
        'guatemala' => 'Guatemala',
        'centro'    => 'Centro',
        'occidente' => 'Occidente',
        'costa'     => 'Costa Sur',
        'oriente'   => 'Oriente',
    ];
}

/** Región por nombre de tienda, mientras la tienda no tenga la suya guardada. */
function dlp_fe_rs_region_defecto($titulo) {
    $t = strtolower(remove_accents($titulo));
    $mapa = [
        'centro'    => ['antigua', 'chimaltenango'],
        'occidente' => ['xela', 'coatepeque', 'retalhuleu', 'cuatro caminos', 'huehuetenango'],
        'costa'     => ['escuintla', 'cocales', 'mazate'],
        'oriente'   => ['jalapa', 'chiquimula', 'jutiapa', 'zacapa', 'morales'],
    ];
    foreach ($mapa as $r => $claves) {
        foreach ($claves as $c) {
            if (strpos($t, $c) !== false) {
                return $r;
            }
        }
    }
    return 'guatemala';
}

function dlp_fe_rs_hora($hm) {
    return $hm ? strtolower(wp_date('g:i a', strtotime('2000-01-01 ' . $hm))) : '';
}

/** Tiendas habilitadas con los datos que usa la página. */
function dlp_fe_rs_tiendas() {
    if (!function_exists('dlp_tiendas_store_flags')) {
        return [];
    }
    $dias = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    $hoy  = $dias[(int) wp_date('w')];
    $ahora = (int) wp_date('G') * 60 + (int) wp_date('i');
    $out = [];
    $tiendas = get_posts(['post_type' => 'extra_store', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
    foreach ($tiendas as $s) {
        $f = dlp_tiendas_store_flags($s->ID);
        if (empty($f['enabled']) || get_post_meta($s->ID, '_dlp_ocultar_web', true)) {
            continue;
        }
        $pt = function_exists('dlp_tiendas_store_point') ? dlp_tiendas_store_point($s->ID) : null;
        if (!$pt) {
            $la = get_post_meta($s->ID, 'extra_store_lat', true);
            $lo = get_post_meta($s->ID, 'extra_store_lng', true);
            $pt = is_numeric($la) && is_numeric($lo) ? [(float) $la, (float) $lo] : null;
        }
        $abierto = false;
        $cierra = '';
        $abre = '';
        foreach (['delivery', 'pickup'] as $tipo) {
            if (empty($f[$tipo])) {
                continue;
            }
            $h  = dlp_tiendas_get_horario($s->ID, $tipo);
            $ini = $h[$hoy][0] ?? '';
            $fin = $h[$hoy][1] ?? '';
            $st = dlp_tiendas_service_status($s->ID, $tipo);
            if (!empty($st['ok'])) {
                $abierto = true;
                if ($fin !== '' && ($cierra === '' || $fin > $cierra)) {
                    $cierra = $fin;
                }
            } elseif ($ini !== '' && ($m = dlp_tiendas_hm($ini)) !== null && $m > $ahora && ($abre === '' || $ini < $abre)) {
                $abre = $ini;
            }
        }
        $estado = $abierto ? 'Abierto · hasta ' . dlp_fe_rs_hora($cierra) : ($abre !== '' ? 'Cerrado · abre a las ' . dlp_fe_rs_hora($abre) : 'Cerrado por hoy');
        $region = get_post_meta($s->ID, '_dlp_region', true);
        if (!isset(dlp_fe_rs_regiones()[$region])) {
            $region = dlp_fe_rs_region_defecto($s->post_title);
        }
        $tel = trim((string) get_post_meta($s->ID, 'extra_store_phone', true));
        $out[] = [
            'id'       => (int) $s->ID,
            'nombre'   => $s->post_title,
            'direccion' => trim((string) get_post_meta($s->ID, 'extra_store_address', true)),
            'tel'      => $tel,
            'lat'      => $pt ? (float) $pt[0] : null,
            'lng'      => $pt ? (float) $pt[1] : null,
            'delivery' => !empty($f['delivery']),
            'pickup'   => !empty($f['pickup']),
            'abierto'  => $abierto,
            'estado'   => $estado,
            'region'   => $region,
        ];
    }
    usort($out, function ($a, $b) {
        $r = array_keys(dlp_fe_rs_regiones());
        return [array_search($a['region'], $r), $a['nombre']] <=> [array_search($b['region'], $r), $b['nombre']];
    });
    return $out;
}

function dlp_fe_rs_tel_href($tel) {
    $d = preg_replace('/\D+/', '', $tel);
    return $d === '' ? '' : 'tel:+502' . ltrim(substr($d, -8), '+');
}

function dlp_fe_rs_url_maps($t) {
    $q = $t['lat'] !== null ? $t['lat'] . ',' . $t['lng'] : rawurlencode('Del Puente ' . $t['nombre'] . ', ' . $t['direccion'] . ', Guatemala');
    return 'https://www.google.com/maps/search/?api=1&query=' . $q;
}

function dlp_fe_rs_url_waze($t) {
    return $t['lat'] !== null
        ? 'https://waze.com/ul?ll=' . $t['lat'] . ',' . $t['lng'] . '&navigate=yes&zoom=17'
        : 'https://waze.com/ul?q=' . rawurlencode('Del Puente ' . $t['nombre'] . ', ' . $t['direccion'] . ', Guatemala') . '&navigate=yes';
}

add_filter('body_class', function ($c) {
    if (dlp_fe_rs_is_page()) {
        $c[] = 'dlprs-page';
    }
    return $c;
});

const DLP_FE_RS_ICO_MAPA  = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a7 7 0 00-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 00-7-7z" fill="#ea4335"/><circle cx="12" cy="9" r="2.6" fill="#fff"/></svg>';
const DLP_FE_RS_ICO_WAZE  = '<svg width="18" height="18" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c-4.6 0-8 3.3-8 7.4 0 1.3.3 2.4.8 3.4-.5.9-1.2 1.4-1.9 1.7 1.2.4 2.5.2 3.6-.4.9.5 2 .8 3.2.9.3 1.4 1.7 2.5 3.3 2.5 1.9 0 3.3-1.4 3.4-3.1 2-1 3.6-3 3.6-5C20 6.3 16.6 3 12 3z" fill="#33ccff"/><circle cx="9.5" cy="9.5" r="1" fill="#111"/><circle cx="14.5" cy="9.5" r="1" fill="#111"/><path d="M9.5 12.2c1.4 1.2 3.6 1.2 5 0" stroke="#111" stroke-width="1.2" fill="none" stroke-linecap="round"/></svg>';
const DLP_FE_RS_ICO_TEL   = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#111" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4h3l2 5-2.5 1.5a11 11 0 005 5L14 13l5 2v3a2 2 0 01-2 2A14 14 0 013 6a2 2 0 012-2z"/></svg>';

add_filter('the_content', function ($content) {
    if (!dlp_fe_rs_is_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $tiendas = dlp_fe_rs_tiendas();
    $reg     = dlp_fe_rs_regiones();
    ob_start();
    ?>
    <div class="dlprs" id="dlprs">
        <header class="dlprs-head">
            <div class="dlprs-ti">
                <span class="dlprs-eye"><?php echo esc_html(dlp_fe_rs_cfg('eyebrow')); ?></span>
                <h1><?php echo esc_html(dlp_fe_rs_cfg('titulo')); ?></h1>
                <p><?php echo esc_html(dlp_fe_rs_cfg('subtitulo')); ?></p>
            </div>
            <div class="dlprs-tools">
                <label class="dlprs-q"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#71717a" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg><input type="search" placeholder="Busca por ciudad o zona" aria-label="Buscar restaurante" autocomplete="off"></label>
                <button type="button" class="dlprs-near"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="8"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>Cerca de mí</button>
            </div>
        </header>
        <div class="dlprs-chips" role="group" aria-label="Filtros">
            <button type="button" class="on" data-k="todos">Todos</button>
            <button type="button" data-k="delivery">Delivery</button>
            <button type="button" data-k="pickup">Pickup</button>
            <button type="button" data-k="abierto">Abierto ahora</button>
            <i></i>
            <?php foreach ($reg as $k => $l) : ?>
                <button type="button" data-r="<?php echo esc_attr($k); ?>"><?php echo esc_html($l); ?></button>
            <?php endforeach; ?>
        </div>
        <div class="dlprs-body">
            <section class="dlprs-list" aria-label="Lista de restaurantes">
                <div class="dlprs-bar"><span class="dlprs-bar-t"></span><button type="button" class="dlprs-bar-x" hidden>Quitar</button></div>
                <ol class="dlprs-items">
                    <?php foreach ($tiendas as $t) :
                        $tel = dlp_fe_rs_tel_href($t['tel']);
                        $txt = strtolower(remove_accents($t['nombre'] . ' ' . $t['direccion'] . ' ' . $reg[$t['region']]));
                        ?>
                        <li class="dlprs-card" data-id="<?php echo (int) $t['id']; ?>" data-r="<?php echo esc_attr($t['region']); ?>" data-d="<?php echo $t['delivery'] ? 1 : 0; ?>" data-p="<?php echo $t['pickup'] ? 1 : 0; ?>" data-o="<?php echo $t['abierto'] ? 1 : 0; ?>" data-txt="<?php echo esc_attr($txt); ?>">
                            <div class="dlprs-c1"><h2><?php echo esc_html($t['nombre']); ?></h2><b class="dlprs-km"></b></div>
                            <?php if ($t['direccion'] !== '') : ?><p class="dlprs-ad"><?php echo esc_html($t['direccion']); ?></p><?php endif; ?>
                            <div class="dlprs-c2">
                                <span class="dlprs-st <?php echo $t['abierto'] ? 'ok' : 'no'; ?>">● <?php echo esc_html($t['estado']); ?></span>
                                <span class="dlprs-sv"><?php echo esc_html(implode(' · ', array_filter([$t['delivery'] ? 'Delivery' : '', $t['pickup'] ? 'Pickup' : '']))); ?></span>
                                <span class="dlprs-ico">
                                    <a href="<?php echo esc_url(dlp_fe_rs_url_maps($t)); ?>" target="_blank" rel="noopener" aria-label="Abrir mapa de <?php echo esc_attr($t['nombre']); ?>"><?php echo DLP_FE_RS_ICO_MAPA; ?></a>
                                    <a href="<?php echo esc_url(dlp_fe_rs_url_waze($t)); ?>" target="_blank" rel="noopener" aria-label="Cómo llegar a <?php echo esc_attr($t['nombre']); ?> con Waze"><?php echo DLP_FE_RS_ICO_WAZE; ?></a>
                                    <?php if ($tel) : ?><a href="<?php echo esc_url($tel); ?>" aria-label="Llamar al <?php echo esc_attr($t['tel']); ?>"><?php echo DLP_FE_RS_ICO_TEL; ?></a><?php endif; ?>
                                </span>
                            </div>
                            <div class="dlprs-full">
                                <a href="<?php echo esc_url(dlp_fe_rs_url_maps($t)); ?>" target="_blank" rel="noopener"><?php echo DLP_FE_RS_ICO_MAPA; ?>Abrir mapa</a>
                                <a href="<?php echo esc_url(dlp_fe_rs_url_waze($t)); ?>" target="_blank" rel="noopener"><?php echo DLP_FE_RS_ICO_WAZE; ?>Cómo llegar</a>
                                <?php if ($tel) : ?><a href="<?php echo esc_url($tel); ?>"><?php echo DLP_FE_RS_ICO_TEL; ?><?php echo esc_html($t['tel']); ?></a><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="dlprs-empty" hidden>No encontramos restaurantes con esos filtros.</p>
            </section>
            <div class="dlprs-map">
                <div id="dlprs-map" role="region" aria-label="Mapa de restaurantes"></div>
                <div class="dlprs-pick"><button type="button" class="dlprs-pickbtn">Elegir un punto en el mapa</button><span class="dlprs-msg" role="status" aria-live="polite"></span></div>
                <div class="dlprs-leg"><span><i class="p"></i>Restaurante</span><span><i class="c"></i>Varias tiendas (toca para acercar)</span></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}, 99);

add_action('wp_enqueue_scripts', function () {
    if (!dlp_fe_rs_is_page() || !defined('DLP_TIENDAS_URL') || !function_exists('dlp_tiendas_map_config') || !wp_script_is('dlp-fe-restaurantes', 'enqueued')) {
        return;
    }
    $u = DLP_TIENDAS_URL . 'assets/vendor/';
    wp_enqueue_style('dlp-leaflet', $u . 'leaflet.css', [], '1.9.4');
    wp_enqueue_script('dlp-leaflet', $u . 'leaflet.js', [], '1.9.4', true);
    wp_enqueue_script('dlp-protomaps', $u . 'protomaps-leaflet.js', ['dlp-leaflet'], '5.1.0', true);
    $datos = array_map(function ($t) {
        return ['id' => $t['id'], 'n' => $t['nombre'], 'a' => $t['direccion'], 'lat' => $t['lat'], 'lng' => $t['lng'], 'r' => $t['region']];
    }, dlp_fe_rs_tiendas());
    wp_localize_script('dlp-fe-restaurantes', 'dlpRs', array_merge(dlp_tiendas_map_config(), ['tiendas' => $datos]));
}, 20);

/* Caja en la tienda: región y ocultar de la página */
add_action('add_meta_boxes_extra_store', function () {
    add_meta_box('dlp-rs', 'Página Restaurantes', function ($post) {
        $r = get_post_meta($post->ID, '_dlp_region', true);
        $r = isset(dlp_fe_rs_regiones()[$r]) ? $r : dlp_fe_rs_region_defecto($post->post_title);
        wp_nonce_field('dlp_rs', 'dlp_rs_nonce');
        echo '<p><label><strong>Región</strong><br><select name="dlp_region" style="width:100%">';
        foreach (dlp_fe_rs_regiones() as $k => $l) {
            echo '<option value="' . esc_attr($k) . '" ' . selected($r, $k, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></label></p><p><label><input type="checkbox" name="dlp_ocultar_web" value="1" ' . checked((bool) get_post_meta($post->ID, '_dlp_ocultar_web', true), true, false) . '> No mostrar en la página Restaurantes</label></p>';
    }, 'extra_store', 'side');
});
add_action('save_post_extra_store', function ($id) {
    if (!isset($_POST['dlp_rs_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['dlp_rs_nonce'])), 'dlp_rs') || !current_user_can('edit_post', $id)) {
        return;
    }
    $r = isset($_POST['dlp_region']) ? sanitize_key(wp_unslash($_POST['dlp_region'])) : '';
    if (isset(dlp_fe_rs_regiones()[$r])) {
        update_post_meta($id, '_dlp_region', $r);
    }
    !empty($_POST['dlp_ocultar_web']) ? update_post_meta($id, '_dlp_ocultar_web', 1) : delete_post_meta($id, '_dlp_ocultar_web');
});
