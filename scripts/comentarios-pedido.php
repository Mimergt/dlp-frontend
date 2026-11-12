<?php
/**
 * Comentarios solo en la página de pago (feedback del cliente).
 *  - Apaga el campo «Comentarios» de la ventana de producto (módulo home-quickview, ajuste «comentarios»).
 *  - Deja el campo de notas del checkout (order_comments) con el texto nuevo, para que el cliente solo escriba especificaciones de su orden.
 * Uso: wp eval-file scripts/comentarios-pedido.php   (idempotente)
 */
$o = get_option('dlp_fe_modules', []);
$o['home-quickview']['settings']['comentarios'] = 0;
update_option('dlp_fe_modules', $o);
echo "home-quickview.comentarios = 0\n";

$items = get_option('dlp_fe_checkout_fields');
if (is_array($items) && $items) {
    foreach ($items as $k => $it) {
        if (($it['key'] ?? '') === 'order_comments') {
            $items[$k]['label'] = 'Comentarios para tu pedido';
            $items[$k]['placeholder'] = 'Escríbenos lo que necesitemos para entregarte: código de acceso, dejarlo en recepción, o alguna especificación sobre tu orden.';
            $items[$k]['enabled'] = 1;
            echo "checkout order_comments actualizado\n";
        }
    }
    update_option('dlp_fe_checkout_fields', $items);
} else {
    echo "checkout: sin campos guardados; se usan los de fábrica (ya traen el texto nuevo)\n";
}
