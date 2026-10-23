# Bitácora de depuración (dev) y lista para producción

Regla: dev primero; **desactivar → probar → borrar**; respaldo antes de borrar (`~/dlp-backups/` en el servidor); cada paso se anota aquí para repetirlo en producción cuando toque. Mapa general: `ARQUITECTURA.md`.

## Hecho en dev
| Fecha | Qué | Respaldo | Prueba posterior | Para producción |
|---|---|---|---|---|
| 2026-10-23 | `wp-file-manager` y `download-plugins-dashboard`: ya no estaban en dev (retirados antes por el usuario) | — | — | **Quitar `wp-file-manager`** (vulnerabilidades graves) |
| 2026-10-23 | Tema `twentytwentyfive` borrado | `tema-twentytwentyfive.tgz` | humo OK | Borrarlo |
| 2026-10-23 | Tema legado `dlp` 2.0.0 borrado (dlp26 ya tenía los mismos theme mods: menú 30; 0 referencias en contenido/opciones) | `tema-dlp-legado-2.0.0.tgz` | humo + pedido de prueba OK | **Solo cuando producción use DLP26** (hoy sigue en `dlp`) |
| 2026-10-23 | 4 `.zip` sueltos en `wp-content/plugins/` movidos a `~/dlp-backups/zips-sueltos-plugins/` (eran **descargables públicamente**: HTTP 200 — `bk_manejoPedidos2.zip`, `manejoPedidos21.zip`, `wp-custom-push-notifications.zip`, `epicpay-dlp-neonet-void.zip`) | misma carpeta | ahora 404 | **Revisar producción** por zips sueltos en `wp-content/plugins/` y `wp-content/` |

Pruebas de humo usadas: `/`, `/menu/`, `/finalizar-compra/`, `/mi-cuenta/`, `/carrito/`, `/orders/`, `/tienda/`, `/wp-login.php` (código HTTP y avisos PHP) + un pedido de delivery de prueba de extremo a extremo (se borra).

## Decisión del usuario (2026-10-23)
**Los plugins se dejan para el final**: hay que ver cuáles se pueden reemplazar y cuáles conviene conservar. Los de WooFood sí se van. Mientras tanto, lo importante es que los activos no interfieran.

## Auditoría de interferencia de los plugins activos (dev, solo lectura)
- Sin errores nuevos: PHP sin avisos en las páginas clave; `WP_DEBUG` apagado; el `error_log` no tiene nada reciente.
- **Ningún plugin de WooFood carga nada** (no hay scripts/estilos ni hooks suyos en home, menú, checkout, Mi cuenta ni panel).
- Hooks cruzados vigentes: `woocommerce_checkout_fields`/`billing_fields` los toca dlp-frontend **y** dlp-tiendas (conviven, probado con 23 pedidos de prueba); `woocommerce_add_to_cart_validation` lo comparten dlp-frontend (`extras-grupos`) y Product Add-Ons (conviven).
- `peters-login-redirect` engancha `login_redirect` pero sin reglas configuradas (solo `rul_settings`): hoy no hace nada; `user-blocker` engancha `authenticate` (bloquea usuarios por rol/fecha; sin tabla ni reglas visibles).
- Pasarelas activas en dev: solo `cod` (contra entrega).
- **Costo sin necesidad** (candidatos para cuando se decida): `anythingslider-divi` carga en TODAS las páginas (checkout, Mi cuenta, panel) aunque solo se usa en el menú; Product Add-Ons carga en Mi cuenta y panel; `handl-utm-grabber` en todas; **Jetpack tiene 15 módulos activos** (stats, woocommerce-analytics, notes, monitor, blaze, ai, subscriptions, activity-log, backup, account-protection…) y solo hace falta Photon/CDN. Se pueden recortar **sin quitar plugins** (apagar módulos de Jetpack o desencolar assets por página desde dlp-frontend).


## Revisión de `dlp-26-functions.php` y de los 29 fragmentos (2026-10-23)
**Jetpack:** desactivado por el usuario. Home, menú, checkout y panel siguen respondiendo sin avisos; las imágenes se sirven desde el origen (sin CDN de Jetpack).

**`dlp-26-functions.php` (2.735 líneas) → dónde quedó cada cosa**
| Función del archivo | Ahora |
|---|---|
| Enqueue padre/select2/locationpicker/custom_script | Borrado (no se usa) |
| Zona→tienda (`dlp26_parse_zone_store_map`, `new_store_meta` y el `switch` de ~2.000 líneas, `dlp26_get_delivery_filters`) | **dlp-tiendas** (polígonos); el ajax de filtros ya no existe ni lo usa nada |
| Botón «ya estoy aquí» + `change_order_status` | dlp-frontend `pedido-recoger` (con llave del pedido) |
| `ds_layout_sc`, `admin_body_class` | Borrado (0 usos) |
| Campos del checkout, dirección opcional, pago bajo las notas | `checkout-campos`, `checkout-layout`, `checkout-campos-editor` |
| `calculo_tiempos` (`tiempo_total`), `device`/OS | `pedidos-meta` |
| Login de la app (`oauth-functions.php`), CORS | `app-login` |
| Teléfono en Editar cuenta, avisos de contraseña | `cuenta-cliente`, `paginas-extra` |
| Cliente invitado por correo / crear cuenta | `checkout-campos` (+ dlp-tiendas para pedidos del chat) |
| Nombre de tienda en el pedido (`add_store_name_to_order`, `update_store_name_if_changed`, lista fija de 33 tiendas) | dlp-tiendas y panel escriben `extra_store_name` + `tienda_asignada` a la vez (comprobado en los pedidos de 40 días). La lista fija **tenía IDs equivocados** (Reu 44936≠44926, Chiquimula 14197≠141967, 103619 «Salcaja») → en producción esos pedidos salen como «Tienda Desconocida». |
| `validate_billing_fields` (zona/depto) | dlp-tiendas valida cobertura |

**Huecos encontrados y corregidos (0.29.5 y 0.29.6)**
1. El JS «nit22» ponía `C/F` y `Consumidor Final` si el NIT/nombre iban vacíos → ahora se hace en el servidor (módulo `checkout-campos`).
2. El panel/historial leen `billing_nit`, `billing_nitname` y `billing_address_name` **sin guion bajo** (los dejaba el plugin viejo de campos) y WooCommerce guarda `_billing_nit`… → pedidos nuevos mostrarían NIT vacío en el panel. Ahora se guardan las dos formas. Probado con un pedido de prueba (borrado).
No se recrea: `tienda_previa` (la escribía WooFood Multistore) ni `_previous_extra_store_name` (marca interna de la función vieja).

**Los 29 fragmentos de Custom CSS & JS: ninguno hace falta en dev.** El plugin ya está inactivo y la web funciona sin ellos. Agrupados:
- WooFood/Multistore (modal, selector de tienda, `wf_*`, select2 del popup, extras acordeón): `all`, `mobile`, `Checkout`, `checkout 22`, `auto close`, `CustomJS`, `customcss`, `wf-extra`, `custom` (parte), `newfixes` (parte).
- Pasarelas viejas (Cybersource/ePay): `card22`, `paymentbox`.
- Panel de motoristas y calificación del plugin viejo (ya no existen): `moto`, `Paneles21`, `ratecss`, `Micuenta-Orders` (tracker).
- Login/Mi cuenta atados a `page-id-9`, ahora en módulos propios: `passwordreset`, `mi cuenta`, `loginmodal`.
- Menú móvil/cabecera (ahora `header-menu`): `Mobile Menu`, `header-cart`.
- Comportamientos ya cubiertos: `nit22` (servidor), `NombreDireccion` (la libreta guarda el nombre), `checkbox&radio` (checkout-estilo), `Admin Panel` (store users usan `/orders/`).
- Restos de pruebas sin efecto: `minicart`, `texts fixes`, `autofill_postalcode`, `disable_autocomplete`.
Antes de borrar el plugin en producción: confirmar que producción ya usa el checkout/menú nuevos.

## Pendiente (en este orden)
1. (AL FINAL, por decisión del usuario) Plugins inactivos (ver lista en `ARQUITECTURA.md` §5.2) y los activos que se puedan reemplazar, de a pocos con prueba después de cada grupo.
2. Opciones huérfanas autoload (`wpnotif_gateway_customer_notifications` 1,3 MB, `berocket_ee`, `brainstrom_bundled_products`) y tareas cron de plugins inexistentes.
3. Drop-in `object-cache.php` (LiteSpeed, plugin inactivo).
4. Revisar los 29 fragmentos `custom-css-js` antes de borrarlos.
5. Datos WooFood (`extra_option`, `woofood_meta`) y `wc_order_status` huérfanos: al final.
