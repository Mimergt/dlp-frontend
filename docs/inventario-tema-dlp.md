# Inventario del tema `dlp` (dev.delpuente.com.gt) — 2026-10-05

Solo lectura. Nada se ha movido ni activado. Destino propuesto: **DLP26** (hijo vacío) · **dlp-frontend** (presentación) · **dlp-funciones** (lógica de negocio/pedidos) · **borrar**.

Contexto: Divi 5.14.0 · WooCommerce 11.1.2 · el tema activo es `dlp` v2.0.0 y `dlp26` existe inactivo.

## 1. `functions.php` (550 líneas)

| Bloque | Qué hace | Destino |
|---|---|---|
| `my_theme_enqueue_styles` | Encola el `style.css` del padre. Divi 5 ya lo hace solo. | **Borrar** |
| Enqueue select2 (CDN), `locationpicker` (CDN, en `<head>`), `custom_script.js` con `?var=time()` | `locationpicker` era del mapa de Google, ya eliminado. `time()` rompe la caché en cada carga. `custom_script.js` solo tiene comentarios. | **Borrar** (select2 solo si algo lo usa; si sí, módulo de dlp-frontend con versión fija) |
| Botón "Ya estoy aquí por mi pedido" (`woocommerce_thankyou`, `order_details_after_order_table`, ajax `change_order_status`) | Pasa pedidos pickup de `dlv` a `rtp`. Mezcla HTML, JS inline y cambio de estado. El ajax es público (`nopriv`), sin nonce. | **dlp-funciones** (el ajax y el estado) + **dlp-frontend** (botón y mensajes) |
| `admin_body_class` con rol | Solo admin. | dlp-funciones |
| Shortcode `ds_layout_sc` + columna en `et_pb_layout` | Atajo de módulos globales de Divi. | dlp-funciones (confirmar si se usa) |
| `woocommerce_checkout_fields`: quita `user_bday` y `shipping_first_name` si hay sesión; quita `billing_postcode`; `address_1` no obligatorio | Campos del checkout. | dlp-funciones |
| `woocommerce_checkout_payment` movido debajo de "notas del pedido" | Reordena el checkout. | dlp-frontend (o plantilla) |
| `calculo_tiempos` | Guarda `tiempo_total` al completar un pedido. | dlp-funciones |
| `add_store_name_to_order` / `update_store_name_meta` | Mapa fijo ID → nombre de tienda (32 tiendas). Duplica datos que ya están en `dlp-tiendas`. | **dlp-tiendas** (que lea el nombre de la tienda, no una lista fija) |
| `yc_save_device_os_to_order` + mostrar en admin | Detecta Android / IOS / MWeb. | dlp-funciones |
| Teléfono en "Editar cuenta" (campo + validación + guardado) | Cuenta de cliente. | dlp-funciones (campo) / dlp-frontend (estilo) |
| `redirect_after_passrecovery` / `redirect_after_passreset` | Hacen `echo` de un `<script alert>` en `init` y `exit`. Frágil. | dlp-frontend (aviso) + redirección en dlp-funciones |
| `action_woocommerce_new_order`, `tn_checkout_create_acct`, `tn_checkout_set_customer_id` | Asignan el pedido invitado a una cuenta existente por correo y crean cuenta. | dlp-funciones |
| `add_cors_http_header` (`Access-Control-Allow-Origin: *` en todo `init`) | CORS abierto a todo el sitio. | **Revisar**; si lo necesita la app, limitarlo a rutas concretas |
| `date_default_timezone_set('UTC')` | Cambia la zona horaria de PHP para todo el sitio. | **Revisar** (puede afectar fechas de pedidos) |

## 2. `inc/oauth-functions.php` (login desde la app Android/iOS)

- `custom_register_from_app` (ajax), `ws_custom_register_from_app` (REST `GET /auth/v1/register_from_app/`) y un autologin por URL (`?autologin=1&hash=...`).
- La contraseña viaja en base64 dentro de la URL o en un GET. Base64 no es cifrado: queda en logs del servidor y del navegador.
- El autologin corre al incluir el archivo y no valida nada más. Con `WP_DEBUG` genera el aviso `Undefined array key "autologin"`, que ya sale en WP-CLI.
- **Destino: dlp-funciones**, o plugin aparte, pero **no migrarlo tal cual**. Hay que ver con quien mantiene la app cómo se usa antes de tocarlo. Mientras tanto debe seguir funcionando.

## 3. `custom_script.js` y `style.css`
- `custom_script.js`: 3 líneas de comentario, vacío. **Borrar**.
- `style.css`: solo cabecera. Nada que migrar.

## 4. Plantillas `woocommerce/`

WooCommerce está en 11.1.2 y estas plantillas declaran versiones viejas (checkout 3.5.0, thankyou 3.7.0, order-details 4.6.0), así que probablemente WooCommerce ya avisa de "plantillas desactualizadas".

| Archivo | Nota | Destino |
|---|---|---|
| `checkout/form-checkout.php` (v3.5.0) | Mete `woocommerce_checkout_before_order_review` en un `div.selec-del` arriba. | Reemplazar por hooks + CSS en dlp-frontend; si hace falta, plantilla actualizada |
| `checkout/thankyou.php`, `order/order-details.php`, `order/tracking.php`, `myaccount/orders.php`, `emails/*` | Sin diff contra WooCommerce 11 todavía. | Comparar con la versión actual y quedarse solo con las diferencias reales |
| `form-checkout-bk.php`, `form-checkoutzzz.php`, `orders.gustavo.php` | Copias de respaldo, WooCommerce no las usa. | **Borrar** |

Además existen `functions copy.php` y `functions copy 2.php` en la carpeta del tema. Hay que borrarlos.

## 5. CSS adicional del Customizer (7.3 KB, 1 post `custom_css`)

No vive en el tema: vive en la base de datos (`wp_get_custom_css()`) y **se pierde al cambiar de tema** si no se copia. Hay que migrarlo a un módulo `base` de dlp-frontend.

Bloques por tipo (algunas reglas son de plugins que hoy están inactivos y probablemente son código muerto, a confirmar mirando el DOM real):
- **Muertos probables** (WooFood, inactivo): `#wf_availability_popup`, `.wf_availability_actions`, `.availability-result`, `.woofood-multistore-store-detail-thx`, `.zhours_alertbar`, `#modal-store-info`, `.store-info*`, `input.get_current_location`.
- **De plugins que no aparecen en la lista**: `.wcmca_*`, `#afreg_additionalshowhide_*`, `p.woocommerce-simple-registration-login-link` (plugin inactivo).
- **Vivos probables**: botón y direcciones de Fr Address Book (`#tsm_*`, `.one-address`, `a.address-link`), header (`.et-cart-info`, `.mobile_menu_bar`, `.et_slide_menu_top`), tabla de pedidos, mensajes de WooCommerce, slider (`anythingslider-divi`).
- **Página de login** (`.page-id-9`, fondo negro, inputs transparentes): ~60 líneas atadas a un ID de página fijo. En dlp-frontend es mejor atarlo a `account` en lugar del ID.
- Reglas frágiles (selectores `a.et_pb_button.et_pb_button_0...`, `top: 450;` sin unidad): se revisan al migrar.

## 6. Otros ajustes que viven en la base de datos y se pierden al cambiar de tema
- `theme_mods_dlp`: `nav_menu_locations` (`primary-menu` = menú "Menu Principal", id 30), `custom_css_post_id` 1701, widgets (todas las barras laterales vacías salvo un `media_image` inactivo).
- Opciones de Divi (`et_divi`): CSS personalizado, JS de cabecera/cuerpo/pie → **vacíos**.
- Esto se copia con WP-CLI al activar `dlp26` (theme mods + asignación de menús).

## 7. Lo que no cubre este inventario
- Opciones de Divi Theme Builder / plantillas (header, footer, producto). Son datos, no van en el tema, pero hay que confirmar que siguen asignadas.
- Reglas de CSS dentro de módulos de Divi (CSS por página). Se pueden revisar página por página.
- `dlp-26-functions.php` del repo `dlp_funciones` repite casi todas las funciones de arriba (incluida la lista de tiendas en la línea ~2501) y tiene ~2700 líneas. No sé si ese archivo es hoy el `functions.php` de producción. **Hay que aclararlo antes de migrar nada**: si se cargan los dos a la vez habrá errores de funciones declaradas dos veces.

## 8. Plugins activos en dev que afectan al front (para decidir cuáles se quedan)
Activos: anythingslider-divi, woo-checkout-field-editor-pro, contact-form-7, disable-emails, dlp-frontend, dlp-panel3, dlp-tiendas, epicpay-dlp-neonet-void, fr-address-book-for-woocommerce, handl-utm-grabber, jetpack, loco-translate, peters-login-redirect, rearrange-woocommerce-products, svg-support, theme-my-login, user-blocker, woocommerce, woocommerce-legacy-rest-api, load-more-products-for-woocommerce, woocommerce-product-addons, woocommerce-side-cart-premium, wp-file-manager, duplicate-page, redirection.
Inactivos que dejaste por probar: woofood-plugin y multistore, custom-css-js-pro, qty-increment-buttons, woocommerce-simple-registration, yith customize myaccount, litespeed-cache, etc.
Nota: `wp-file-manager` activo (histórico de vulnerabilidades graves) y `woocommerce-legacy-rest-api` merecen revisión aparte.

---

## 9. Decisiones y migración (2026-10-05)

Lo que cubren los plugins → **se borra del tema**: lista de tiendas ID→nombre (`add_store_name_to_order` y compañía; `dlp-tiendas` y `dlp-panel3` ya guardan `extra_store_name` y `tienda_asignada`), `new_store_meta`, `validate_billing_fields` (solo estaban en el `dlp-26-functions.php` legacy; `dlp-tiendas` ya las desactiva).

Se borra por muerto/inútil: enqueue del padre, select2, locationpicker, `custom_script.js`, `date_default_timezone_set`, shortcode `ds_layout_sc` (0 usos en dev), clase `role-*` del admin, plantillas de respaldo, `tracking.php` (tenía un `<h1>` de depuración), `orders.php` / `order-details.php` / correo de estado (llaman a `[ver_tracker]` del plugin viejo `manejodepedidos2`, que ya no está activo).

Seguridad corregida: `change_order_status` ahora exige la llave del pedido y solo permite dlv→rtp en pedidos de recoger; CORS `*` limitado a los endpoints de la app; los datos de una cuenta ya no se sobrescriben cuando un invitado usa su correo.

Pasa al plugin como módulos `boot`: `app-login` (oauth tal cual), `checkout-campos`, `checkout-layout` (reemplaza el override de `form-checkout.php`), `cuenta-cliente`, `pedidos-meta`, `pedido-recoger`, `plantillas-woocommerce` (`thankyou.php` minimalista) y `base` (CSS).

Pendiente para después: revisar el CSS adicional (muerto vs vivo), reemplazar `thankyou` por hooks, y decidir qué hacer con "Califica este pedido" y el rastreo, que dependían del plugin viejo.
