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

## Pendiente (en este orden)
1. Plugins inactivos (ver lista en `ARQUITECTURA.md` §5.2), de a pocos con prueba después de cada grupo.
2. Opciones huérfanas autoload (`wpnotif_gateway_customer_notifications` 1,3 MB, `berocket_ee`, `brainstrom_bundled_products`) y tareas cron de plugins inexistentes.
3. Drop-in `object-cache.php` (LiteSpeed, plugin inactivo).
4. Revisar los 29 fragmentos `custom-css-js` antes de borrarlos.
5. Datos WooFood (`extra_option`, `woofood_meta`) y `wc_order_status` huérfanos: al final.
