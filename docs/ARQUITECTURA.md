# Arquitectura de Del Puente (dlp-frontend + dlp-tiendas + dlp-panel3) — 2026-10-23

Mapa para entender cómo encajan las tres piezas propias y qué se puede depurar. Está hecho **leyendo dev** (`dev.delpuente.com.gt`) y el código de los tres repos. **Producción sigue en el stack viejo** (tema `dlp`, WooFood, Custom CSS & JS, etc.): todo lo de abajo describe dev. Nada se ha borrado todavía.

## 1. Las tres piezas y qué hace cada una

| Pieza | Repo / versión en dev | Hace | NO hace |
|---|---|---|---|
| **dlp-frontend** | `Mimergt/dlp-frontend` · 0.29.4 · se actualiza solo desde GitHub | Todo lo visual y la UX del cliente: home, menú/pestañas, vista rápida del producto y sus extras (acordeones, `[grupo:]`, `[elegir:]`), checkout (campos, libreta, estilo), gracias, Mi cuenta, login, 404, tema oscuro. Módulos `boot` con la lógica que antes vivía en el `functions.php` del tema (login de la app, campos, cliente invitado, pedido-recoger). | No decide cobertura ni horarios ni tiendas. |
| **dlp-tiendas** (“Geolocalización”) | `Mimergt/dlp-tiendas` (privado) · 0.14.0 · se despliega por rsync (`scripts/deploy-dev.sh`) | Dueña de **tiendas, zonas, horarios, pausas, pickup/delivery, mínimo de pedido, hora de recogida**. Pin en el checkout, validación de cobertura (cliente y servidor), asigna la tienda al pedido. REST `dlp/v1` para el “cerebro” de chat (`mcp_woo`): ubicación por enlace y crear pedido. | No dibuja el checkout (solo el mapa/selector Delivery-Pickup que inyecta). |
| **dlp-panel3** (“Panel de pedidos”, shortcode `[dlp_paneles]`, ruta `/orders/`) | `Mimergt/dlp-panel3` · 1.13.2 | Operación de pedidos para tiendas y supervisores: tablero, estados, prioridad/nota, reasignar tienda, historial, botón “Servicios” (pausar/cubrir). Registra los estados `prep`, `dlv`, `rtp`. | No sabe de cobertura: la **pide** a dlp-tiendas. |

Piezas de terceros que sí forman parte del flujo (hoy): WooCommerce 11, **Product Add-Ons** (extras), Divi 5 + tema hijo vacío **DLP26**, **Xootix Side Cart** (carrito lateral), **anythingslider-divi** (las pestañas de categorías del menú), Jetpack (CDN de imágenes), pasarela de pago (ver preguntas), Redirection (redirecciones de productos).

## 2. Flujo de un pedido (quién toca qué)
1. **Home / Menú** (frontend: `home-nuevo`, `home-portada`, `menu-inferior`) → producto en vista rápida (`home-quickview`, extras de Add-Ons, validación en `extras-grupos`).
2. **Carrito** (Side Cart + `carrito-lateral` solo CSS) → **Checkout** (frontend: `checkout-campos`, `checkout-campos-editor`, `checkout-layout`, `checkout-libreta`, `checkout-estilo`).
3. En el checkout, **dlp-tiendas** inyecta el selector Delivery/Pickup, el mapa y el selector de tienda/hora (`includes/checkout.php`, `pedido.php`); el frontend lo estiliza y su libreta de direcciones guarda lat/lng.
4. Al enviar: `woocommerce_after_checkout_validation` (dlp-tiendas) **bloquea** si no hay cobertura/horario/mínimo; `woocommerce_checkout_create_order` guarda tienda y datos geo en el pedido.
5. **Panel** (dlp-panel3) lee el pedido por sus metas y lo mueve de estado; usa funciones de dlp-tiendas para zona, mapa y servicios.
6. **Gracias / Mi cuenta / pedido-recoger** (frontend) leen las mismas metas (`woofood_order_type`, hora, tienda).

## 3. Contratos entre piezas (lo que NO se puede renombrar a la ligera)

**Metas de pedido** (las escribe dlp-tiendas, las leen panel y frontend):
`extra_store_name` (ID de tienda), `tienda_asignada` (nombre), `woofood_order_type` (`delivery`/`pickup`), `woofood_time_to_deliver` (timestamp de recogida; el checkout la envía como `woofood_time_to_pickup`), `_dlp_lat`, `_dlp_lng`, `_dlp_zona`, `_dlp_zona_id`, `_dlp_geo_source`, `_dlp_tienda_zona` (tienda dueña de la zona si otra la cubre), `_dlp_priority`, `_dlp_internal_note` (panel), `tiempo_total`, `device` y `tienda_previa`/`_previous_extra_store_name` (legado). Item meta histórica: `woofood_meta` (extras de WooFood de pedidos viejos; el panel la sabe leer).
> Los nombres `woofood_*` son **historia que sigue en uso**: aunque WooFood ya no exista, el panel, el frontend, la gracias y los reportes dependen de ellos. Renombrar = migración de 62k pedidos. No tocar sin plan.

**Metas de tienda** (CPT `extra_store`, se conserva por compatibilidad): `extra_store_enabled`, `order_type_delivery`, `order_type_pickup` (existe = activo), `extra_store_lat/lng/address/phone/email/user`, `woofood_{delivery|pickup}_hours_{día}_{start|end}`, `_dlp_zonas` (GeoJSON), `_dlp_principal`, `_dlp_prioridad`, `_dlp_departamento`, `_dlp_pausa_{tipo}`, `_dlp_pausa_log`, `_dlp_cubierta_por/_info`. Usuarios de tienda: rol `multistore_user` (29) + meta de usuario `extra_store_name`; supervisor = `manage_options` o meta `_dlp_paneles_supervisor`.

**Funciones PHP de dlp-tiendas que otros llaman** (si cambian, se rompen):
- Panel: `dlp_tiendas_map_config`, `dlp_tiendas_service_status`, `dlp_tiendas_resolve_store`, `dlp_tiendas_store_flags`, `dlp_tiendas_get_pausa`, `dlp_tiendas_cobertura_info`, `dlp_tiendas_set_cubierta`.
- Frontend: `dlp_tiendas_check_point` (libreta y Mi cuenta), `dlp_tiendas_map_config`, constante `DLP_TIENDAS_URL`, AJAX `dlp_tiendas_check_point` + nonce `dlp_tiendas_checkout`.
- Todos protegen con `function_exists`, así que cada pieza sobrevive si falta otra (se degrada, no se rompe).

**Estados de pedido** (los registra dlp-panel3, `includes/statuses.php`): `wc-prep`, `wc-dlv` (Enviada/LPR), `wc-rtp`. `pedido-recoger` (frontend) pasa `dlv→rtp` en pickup.

**REST/AJAX públicos**: panel `/wp-json/dlp-paneles/v1/*`; tiendas `dlp/v1` (header `X-DLP-Secret`, lo consume `mcp_woo`); app móvil: módulo `app-login` del frontend (REST `auth/v1/register_from_app` + autologin; ver riesgos del inventario del tema).

## 4. Lo que ya se reemplazó (y por qué se puede borrar en dev)
| Antes | Ahora |
|---|---|
| Tema `dlp` (functions.php 550 líneas + `dlp-26-functions.php` ~2700) | Tema hijo vacío `dlp26` + módulos `boot` de dlp-frontend |
| WooFood + WooFood Multistore (tiendas, zonas por desplegable, mínimo, selector, hora) | dlp-tiendas (polígonos, horarios, pausas…) |
| WooFood extras (`extra_option`, 140) | WooCommerce Product Add-Ons (+ generadores `scripts/extras-*.php`) |
| Google Maps/Distance Matrix (llave revocada) | Leaflet + PMTiles propios (R2) |
| Panel `manejodepedidos2` / `dlp-pedidosv2` (+ `[ver_tracker]`) | dlp-panel3 (`/orders/`) |
| Woo Checkout Field Editor, Fr Address Book, Theme My Login, CF7 (ya retirados de dev) | módulos `checkout-campos*`, `checkout-libreta`, `login-pagina`, `cuenta-*` |
| Custom CSS & JS Pro (29 fragmentos), CSS adicional del Customizer | módulos `base`, `checkout-estilo`, `tema`… (verificar que no falte nada antes de borrar los 29) |

## 5. Inventario de depuración (estado en dev, 2026-10-23)

### 5.1 Plugins activos
| Plugin | Veredicto | Evidencia / nota |
|---|---|---|
| woocommerce, woocommerce-product-addons | **Quedan** | Núcleo. |
| dlp-frontend, dlp-tiendas, dlp-panel3 | **Quedan** | Propios. |
| woocommerce-side-cart-premium | **Queda** | El módulo `carrito-lateral` lo estiliza. |
| anythingslider-divi | **Queda** | Son las pestañas del menú (`cwp_et_pb_layout_slider_standard`, 1 página). |
| rearrange-woocommerce-products | Queda (por ahora) | Se usa para ordenar productos (`menu_order`). |
| redirection | **Queda** | Redirecciones de productos/categorías (`paginas-extra`). |
| epicpay-dlp-neonet-void | Queda | Anulación de cobros (26 pedidos con `_epicpay_void_*`). |
| jetpack | Decidir | Solo se usa el CDN de imágenes (filtro `jetpack_photon_skip_image`); es pesado (opciones autoload 100 KB+). Medir alternativa antes. |
| disable-emails | Solo dev | Evita correos en pruebas. Se va cuando se hagan los correos propios (PLAN §4d de dlp-tiendas). |
| handl-utm-grabber | Decidir | Marketing: guarda `handl_*` en pedidos (315/319). ¿Se sigue usando el dato? |
| duplicate-page | Comodidad | Inofensivo. |
| svg-support | Probable salida | 1 SVG en medios; los íconos del plugin son inline. |
| peters-login-redirect | Probable salida | Sin reglas configuradas (`rul_*` vacíos). Confirmar que nada depende de la redirección por rol. |
| user-blocker | Decidir | Sin opciones guardadas visibles; ¿quién lo usa? |
| **wp-file-manager** | **Quitar** | Historial de vulnerabilidades graves; sin uso en el flujo. |

### 5.2 Plugins inactivos (candidatos a borrar de dev; ya fuera de uso)
`download-plugins-dashboard, litespeed-cache (¡ver 5.4!), loco-translate, migrate-guru, credit-paymente-for-woo-cybersource-vn, popup-maker, pwa-for-wp, qty-increment-buttons-for-woocommerce, custom-css-js-pro (ver nota), woocommerce-simple-registration, google-site-kit, gtm-ecommerce-woo, woocommerce-availability-scheduler (idea: disponibilidad por día → ahora tarea de dlp-tiendas), woocommerce-gateway-cybersource, woocommerce-google-analytics-pro, woocommerce-legacy-rest-api, load-more-products-for-woocommerce, woocommerce-order-status-manager, woofood-plugin, woofood-multistore-plugin, wordfence, wp-serverinfo, wp-all-export-pro, wp-mail-smtp, wppusher, wp-rollback, yith-woocommerce-customize-myaccount-page, wordpress-seo`
Reglas: borrar de **dev** primero, de a pocos, con respaldo. Notas: `woofood-*` y `custom-css-js-pro` dejan datos que **producción aún usa** (ver §6); `wp-mail-smtp` y `wordpress-seo` podrían volver a ser necesarios (correos propios, SEO): decidir antes de borrar; Cybersource/Google Analytics/GTM: confirmar qué pasarela y qué analítica usa producción.

### 5.3 Temas
`dlp` (inactivo, legado: borrar cuando se confirme que no queda nada que migrar), `twentytwentyfive` (borrar), `Divi` (padre) y `dlp26` (activo, vacío) quedan.

### 5.4 Residuos del sistema
- `wp-content/object-cache.php` es el *drop-in* de **LiteSpeed Cache**, cuyo plugin está inactivo: revisar si el sitio realmente usa caché de objetos o quedó huérfano.
- `maintenance.php` (drop-in): comprobar que es el de Hostinger.
- **Base de datos**: CPT sin plugin que los use — `extra_option` (140 de WooFood), `wc_order_status` (14: los estados `prep/dlv/rtp` ya los registra el panel; hay 3 de motorista/“accepting” huérfanos), `woo_mb_template` (11), `def_reg_fields` (10), `store_info` (10), `popup`/`popup_theme` (13), `custom-css-js` (29), `afreg_fields`, `wpforms`, `elementor_library`, `sa_slider`. Opciones autoload huérfanas: `wpnotif_gateway_customer_notifications` (**1,3 MB** cargados en cada petición), `berocket_ee` (80 KB), `brainstrom_bundled_products` (37 KB); 142 opciones `yith*`, 52 `wpseo*`, etc. Tareas cron de plugins que ya no existen (`wpnotif`, `updraft`, `wooslg`, `zhours`, `wc_jilt`, `Informe_de_Ordenes_trigger`, export de wp-all-export…).
- Metas de pedido de herramientas fuera de uso (`afreg_*`, `woofood_product_availability`, `checked_extra_stores`, `gtm_ecommerce_woo_purchase_event_tracked`, datos Cybersource): son historia de pedidos; se dejan, no estorban.

### 5.5 Código legado en los repos
- Repo `dlp_funciones` (este): `dlp-26-functions.php` (~2700 líneas, zonas→tienda, lista fija de tiendas), `cutosm_script_26.js`, `dlp-paneles/` (copia vieja del panel), `manejodepedidos2/`, `woofood-plugin/`, `woofood-multistore-plugin/`, `dlp-pedidosv2`, zips, `cobertura-texto.txt`, `COBERTURAS FASE 1 ACTUALIZADO.xlsx`. **Aclarar** si `dlp-26-functions.php` es hoy el `functions.php` de producción antes de tocarlo.
- `dlp-tiendas/referencia/`: copias de legado (no versionadas).

## 6. Riesgos y reglas para depurar
1. **Producción manda.** Todo lo de dev puede borrarse con calma; en producción siguen activos WooFood, Custom CSS & JS, el tema `dlp` y el panel viejo. Cada borrado en dev debe quedar anotado en una **lista de cambios para producción** (con respaldo y orden).
2. Nunca borrar y probar en el mismo paso: **desactivar → probar (home, menú, vista rápida, checkout delivery/pickup, gracias, Mi cuenta, panel) → borrar**.
3. Antes de borrar plugins con datos: respaldo de BD con `mysqldump` (el `wp db export` falla en silencio en este servidor) y de archivos en `~/dlp-backups/`.
4. No renombrar metas `woofood_*` ni el CPT `extra_store`.
5. `extra_option`/`woofood_meta` pueden hacer falta para ver pedidos históricos; la limpieza de datos va **después** y tras confirmar el panel/historial.
6. Solo `dev.delpuente.com.gt` (el servidor aloja otros sitios).

## 7. Preguntas abiertas
- ¿Qué pasarelas de pago están activas hoy en producción? En dev solo hay `epicpay-dlp-neonet-void` activo, pero los pedidos traen datos de Cybersource.
- ¿Quién consume `peters-login-redirect`, `user-blocker` y `handl-utm-grabber`?
- ¿La app Android/iOS sigue usando el login por `autologin` (módulo `app-login`)? Revisar su seguridad con quien la mantiene.
- Correos propios (nuevo pedido, estados, cuenta) y disponibilidad por día: pendientes en dlp-tiendas; hasta entonces no se puede retirar `disable-emails`/`wp-mail-smtp` sin decidir.
- ¿Los 29 fragmentos de `custom-css-js` tienen algo vigente que aún no esté en dlp-frontend? Revisar uno por uno.
