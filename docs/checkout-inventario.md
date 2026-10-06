# Página de pago: qué había en el tema y qué se queda

Base: `docs/inventario-tema-dlp.md`, el checkout vivo en dev (campos reales leídos de la página) y los plugins `dlp-tiendas` / `dlp-panel3`.
Estados: **YA ESTÁ** (migrado al plugin), **LO CUBRE** (otro plugin), **IMPLEMENTAR** (falta presentación nueva), **REVISAR** (hay que decidir), **BACKUP** (se va, queda solo en el respaldo del tema).

## Lógica que ya vive en el plugin

| Qué | Dónde estaba | Estado |
|---|---|---|
| Quitar cumpleaños, código postal y nombre de envío con sesión; dirección no obligatoria | `functions.php` (`woocommerce_checkout_fields`) | **YA ESTÁ** · `checkout-campos` |
| Pedido de invitado asignado a su cuenta por correo / crear cuenta | `tn_checkout_*`, `action_woocommerce_new_order` | **YA ESTÁ** · `checkout-campos` (ya no sobrescribe datos de la cuenta) |
| Selector Delivery/Pickup arriba y métodos de pago debajo de las notas | `form-checkout.php` v3.5.0 + `woocommerce_checkout_payment` | **YA ESTÁ** · `checkout-layout` (el override del tema se borra) |
| Botón «Ya estoy aquí por mi pedido» | `functions.php` + ajax público | **YA ESTÁ** · `pedido-recoger` (ahora exige la llave del pedido) |
| Página de gracias | `checkout/thankyou.php` v3.7.0 | **YA ESTÁ** (minimalista) · `plantillas-woocommerce` |
| `tiempo_total` y dispositivo (Android/IOS/MWeb) | `calculo_tiempos`, `yc_save_device_os_to_order` | **YA ESTÁ** · `pedidos-meta` |
| Teléfono en «Editar cuenta» | `functions.php` | **YA ESTÁ** · `cuenta-cliente` |

## Lo que cubren los plugins de tiendas

| Qué | Cubierto por |
|---|---|
| Mapa, «usar mi ubicación», cobertura y validación antes de crear el pedido | `dlp-tiendas` (`checkout.php`, `checkout-map.js`) |
| Tienda para recoger y hora de llegada | `dlp-tiendas` |
| Nombre de tienda en el pedido (lista fija de 32 tiendas en el tema) | `dlp-tiendas` / `dlp-panel3` (`extra_store_name`, `tienda_asignada`) → **BACKUP** la lista del tema |

## Presentación: lo que falta (la sección nueva)

| Qué | Hoy | Estado |
|---|---|---|
| Aviso rojo «Inicia sesión…» y «¿Tienes un cupón?» | Bloques rojos a todo el ancho | **IMPLEMENTAR** (enlaces discretos o plegables) |
| Delivery / Pickup | Dos radios sueltos sin estilo | **IMPLEMENTAR** (control segmentado, lo estiliza el plugin; la lógica es de `dlp-tiendas`) |
| Caja «Ubicación de entrega» (mapa) | Caja con botones negros cuadrados | **IMPLEMENTAR** (estilo; el contenido es de `dlp-tiendas`) |
| Campos del formulario | Fondo gris, etiquetas pequeñas, sin agrupar | **IMPLEMENTAR** (tarjetas por sección, campos redondeados) |
| Factura (NIT / nombre) | 2 campos sueltos al final | **IMPLEMENTAR** (sección opcional plegada) |
| «Selecciona aquí para crear tu cuenta» y «Pedido en Restaurante» | Casillas sueltas | **IMPLEMENTAR** (como interruptores) |
| Resumen del pedido («Tu pedido») | Tabla al final, debajo del botón | **IMPLEMENTAR** (arriba o fijo, con extras legibles) |
| Métodos de pago + botón «Realizar el pedido» | Caja morada de WooCommerce | **IMPLEMENTAR** (botón píldora fijo abajo con el total) |
| Aviso de privacidad | Texto largo con enlace | **IMPLEMENTAR** (texto pequeño) |

## Por revisar (necesito tu decisión)

| Qué | Duda |
|---|---|
| `agregar_orgien_al_pedido` (`woocommerce_checkout_create_order`, `dlp-26-functions.php`) | Guarda el origen del pedido; no aparece en el inventario anterior. ¿Alguien lo lee (paneles, reportes)? |
| Campo «Pedido en Restaurante» (`additional_enrestaurante`) | Viene del editor de campos del checkout. ¿Se sigue usando o se quita? |
| Campos NIT / nombre de factura / referencia de dirección | Los crea `woo-checkout-field-editor-pro`. ¿Se quedan en ese plugin o pasan al plugin propio? |
| Libreta de direcciones (`fr-address-book-for-woocommerce`, botones `#tsm_*`) | Hay CSS viejo para esos botones. ¿Se sigue usando en el checkout? |
| «Califica este pedido» y rastreo | Dependían de `manejodepedidos2`, que ya no está activo. ¿Se rehacen o se descartan? |
| Pasarela de tarjeta (`epicpay-dlp-neonet-void`) | En dev solo aparece «Pago en efectivo». Hay que decidir cómo se muestra la tarjeta cuando esté activa. |

## BACKUP (solo se conserva en el respaldo del tema)

`form-checkout.php` (override), `form-checkout-bk.php`, `form-checkoutzzz.php`, `tracking.php`, `orders.php`, `order-details.php` y el correo de estado (llaman a `[ver_tracker]`), la lista ID→tienda, `new_store_meta`, `validate_billing_fields` y el CSS viejo de `.wcmca_*` / `#afreg_*`.
