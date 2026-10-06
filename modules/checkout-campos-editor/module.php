<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editor de campos del checkout. La configuración vive en la opción dlp_fe_checkout_fields (lista ordenada).
 * Sin configuración guardada se usan los valores por defecto de dlp_fe_cf_defaults(), que reproducen el checkout actual.
 * Claves compatibles con el plugin anterior (billing_nit, billing_nitname, billing_address_name...), así que los
 * pedidos existentes y los paneles siguen leyendo los mismos datos.
 */
const DLP_FE_CF_OPTION = 'dlp_fe_checkout_fields';

function dlp_fe_cf_defaults() {
    $i = function ($key, $label, $ph, $req, $width, $section, $custom = false, $order = 1, $mail = 0, $group = 'billing', $type = 'text') {
        return [
            'key' => $key, 'group' => $group, 'label' => $label, 'placeholder' => $ph, 'type' => $type, 'required' => $req ? 1 : 0,
            'enabled' => 1, 'width' => $width, 'section' => $section, 'custom' => $custom ? 1 : 0, 'options' => '',
            'show_order' => $order, 'show_email' => $mail, 'locked' => 0,
        ];
    };
    return [
        $i('billing_address_2', 'Escribe tu dirección completa', 'Ej: 2 Ave 3-14, Appt 143. #Acceso: 9999', false, 'full', 'entrega', false, 0),
        $i('billing_address_name', 'Referencia de tu dirección', 'Identifica más fácil. (Ejem: casa, trabajo, etc.)', false, 'full', 'entrega', true, 0),
        $i('billing_first_name', 'Nombre', '', true, 'first', 'datos', false, 0),
        $i('billing_last_name', 'Apellido', 'Apellido', true, 'last', 'datos', false, 0),
        $i('billing_phone', 'Teléfono', 'Ingresa tu teléfono', true, 'full', 'datos', false, 0, 0, 'billing', 'tel'),
        $i('billing_email', 'Dirección de correo electrónico', 'micorreo@ejemplo.com', true, 'full', 'datos', false, 0, 0, 'billing', 'email'),
        $i('billing_nitname', 'Nombre para la factura', 'Nombre de Facturación', false, 'full', 'factura', true, 1, 1),
        $i('billing_nit', 'NIT o C/F', 'Ingresa tu NIT o escribe CF', false, 'full', 'factura', true, 1, 1),
        $i('order_comments', 'Notas del pedido', 'Notas adicionales para su pedido, por ejemplo instrucciones de entrega.', false, 'full', 'factura', false, 0, 0, 'order', 'textarea'),
    ];
}

/** Campos que manejan otros módulos (mapa, tienda): se muestran bloqueados y no se tocan. */
function dlp_fe_cf_locked() {
    return [
        ['key' => 'billing_address_1', 'label' => 'Ubicación en el mapa'],
        ['key' => 'billing_state', 'label' => 'Departamento'],
        ['key' => 'billing_city', 'label' => 'Ciudad / zona'],
    ];
}

function dlp_fe_cf_items() {
    $saved = get_option(DLP_FE_CF_OPTION);
    return is_array($saved) && $saved ? $saved : dlp_fe_cf_defaults();
}

add_filter('woocommerce_checkout_fields', function ($fields) {
    $prio = 20;
    foreach (dlp_fe_cf_items() as $it) {
        $g = $it['group'] === 'order' ? 'order' : 'billing';
        $k = $it['key'];
        if (empty($it['enabled'])) {
            unset($fields[$g][$k]);
            continue;
        }
        $f = isset($fields[$g][$k]) ? $fields[$g][$k] : null;
        if ($f === null) {
            if (empty($it['custom'])) {
                continue;
            }
            $f = ['type' => $it['type'], 'class' => ['form-row-wide'], 'input_class' => [], 'label_class' => []];
        }
        $f['label']       = $it['label'];
        $f['placeholder'] = $it['placeholder'];
        $f['required']    = !empty($it['required']);
        $f['priority']    = $prio;
        $prio += 10;
        $cls = array_values(array_diff((array) ($f['class'] ?? []), ['form-row-first', 'form-row-last', 'form-row-wide']));
        $cls[] = $it['width'] === 'first' ? 'form-row-first' : ($it['width'] === 'last' ? 'form-row-last' : 'form-row-wide');
        $f['class'] = array_values(array_unique($cls));
        if (($f['type'] ?? '') === 'select' || $it['type'] === 'select') {
            $opts = ['' => 'Elige una opción…'];
            foreach (preg_split('/\r\n|\r|\n/', (string) $it['options']) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $opts[$line] = $line;
                }
            }
            $f['type']    = 'select';
            $f['options'] = $opts;
        }
        if ($g === 'billing') {
            $f['custom_attributes'] = array_merge((array) ($f['custom_attributes'] ?? []), ['data-dlp-card' => $it['section']]);
        }
        $fields[$g][$k] = $f;
    }
    foreach (['billing', 'order'] as $g) {
        if (!empty($fields[$g])) {
            uasort($fields[$g], function ($a, $b) {
                return ($a['priority'] ?? 0) <=> ($b['priority'] ?? 0);
            });
        }
    }
    return $fields;
}, 100000);

/** Campos personalizados con "mostrar en el pedido" / "mostrar en el correo". */
function dlp_fe_cf_custom($flag) {
    return array_values(array_filter(dlp_fe_cf_items(), function ($it) use ($flag) {
        return !empty($it['custom']) && !empty($it[$flag]) && !empty($it['enabled']) && $it['group'] === 'billing';
    }));
}

add_action('woocommerce_admin_order_data_after_billing_address', function ($order) {
    foreach (dlp_fe_cf_custom('show_order') as $it) {
        $v = $order->get_meta('_' . $it['key']);
        if ($v !== '' && $v !== null) {
            echo '<p><strong>' . esc_html($it['label']) . ':</strong> ' . esc_html($v) . '</p>';
        }
    }
});

add_filter('woocommerce_email_order_meta_fields', function ($fields, $sent_to_admin, $order) {
    foreach (dlp_fe_cf_custom('show_email') as $it) {
        $v = $order->get_meta('_' . $it['key']);
        if ($v !== '' && $v !== null) {
            $fields[$it['key']] = ['label' => $it['label'], 'value' => $v];
        }
    }
    return $fields;
}, 10, 3);

// ---- Admin ---------------------------------------------------------------------------------------------------

add_action('admin_post_dlp_fe_save_campos', function () {
    if (!current_user_can('manage_options')) {
        wp_die('No autorizado');
    }
    check_admin_referer('dlp_fe_campos');
    $raw   = isset($_POST['campos_json']) ? json_decode(wp_unslash($_POST['campos_json']), true) : null;
    $types = ['text', 'textarea', 'checkbox', 'select', 'tel', 'email'];
    $def   = [];
    foreach (dlp_fe_cf_defaults() as $d) {
        $def[$d['key']] = $d;
    }
    $out   = [];
    $seen  = [];
    if (is_array($raw)) {
        foreach ($raw as $r) {
            $key = isset($r['key']) ? sanitize_key($r['key']) : '';
            if ($key === '') {
                continue;
            }
            $known  = isset($def[$key]);
            $custom = $known ? (int) $def[$key]['custom'] : (!empty($r['custom']) ? 1 : 0);
            if (!$known && strpos($key, 'billing_') !== 0) {
                $key = 'billing_' . $key;
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = 1;
            $out[] = [
                'key'         => $key,
                'group'       => $known ? $def[$key]['group'] : 'billing',
                'label'       => sanitize_text_field($r['label'] ?? ''),
                'placeholder' => sanitize_text_field($r['placeholder'] ?? ''),
                'type'        => in_array($r['type'] ?? '', $types, true) ? $r['type'] : 'text',
                'required'    => !empty($r['required']) ? 1 : 0,
                'enabled'     => !empty($r['enabled']) ? 1 : 0,
                'width'       => in_array($r['width'] ?? '', ['full', 'first', 'last'], true) ? $r['width'] : 'full',
                'section'     => in_array($r['section'] ?? '', ['entrega', 'datos', 'factura'], true) ? $r['section'] : 'datos',
                'custom'      => $custom,
                'options'     => sanitize_textarea_field($r['options'] ?? ''),
                'show_order'  => !empty($r['show_order']) ? 1 : 0,
                'show_email'  => !empty($r['show_email']) ? 1 : 0,
                'locked'      => 0,
            ];
        }
    }
    if ($out) {
        update_option(DLP_FE_CF_OPTION, $out, false);
    }
    wp_safe_redirect(admin_url('themes.php?page=dlp-frontend&tab=campos&saved=1'));
    exit;
});

/** Contenido de la pestaña "Campos del checkout". */
function dlp_fe_cf_render_admin() {
    wp_enqueue_script('dlpfe-campos', DLP_FE_URL . 'modules/checkout-campos-editor/admin.js', [], filemtime(__DIR__ . '/admin.js'), true);
    $data = ['items' => dlp_fe_cf_items(), 'locked' => dlp_fe_cf_locked()];
    ?>
    <div class="dlpfe-head">
        <div><h2>Campos del checkout</h2><p>Etiquetas, textos de ayuda, orden y obligatoriedad de los campos del formulario de pago. Arrastra para ordenar; los cambios se aplican al guardar.</p></div>
        <button type="button" class="dlpfe-btn dlpfe-primary" id="dlpfe-add">+ Nuevo campo</button>
    </div>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="dlpfe-form">
        <input type="hidden" name="action" value="dlp_fe_save_campos">
        <?php wp_nonce_field('dlp_fe_campos'); ?>
        <input type="hidden" name="campos_json" id="dlpfe-json">
        <script type="application/json" id="dlpfe-data"><?php echo wp_json_encode($data); ?></script>
        <div class="dlpfe-cols">
            <div class="dlpfe-card dlpfe-tablecard"><table class="dlpfe-table"><thead><tr><th></th><th>Campo</th><th>Sección</th><th>Visible</th><th>Obligatorio</th><th></th></tr></thead><tbody id="dlpfe-rows"></tbody></table></div>
            <aside class="dlpfe-card dlpfe-edit" id="dlpfe-edit"><div class="dlpfe-empty">Elige un campo con «Editar» para cambiar sus datos.</div></aside>
        </div>
        <p class="dlpfe-save"><button type="submit" class="dlpfe-btn dlpfe-primary">Guardar cambios</button> <span class="dlpfe-hint">Los campos de ubicación (mapa, departamento y zona) los maneja dlp-tiendas y no se editan aquí.</span></p>
    </form>
    <?php
}
