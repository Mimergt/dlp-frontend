# Memoria del proyecto DLP Frontend (Del Puente)

Documento de retoma: léelo completo al empezar una sesión nueva. Última actualización: **2026-10-06**, plugin **v0.15.4** en dev (dlp-tiendas 0.10.1).

## 1. Qué es y dónde vive

| Pieza | Qué es | Dónde |
|---|---|---|
| **dlp-frontend** | Plugin de módulos con todo lo visual y de checkout/cuenta. Se actualiza solo con Plugin Update Checker desde la rama `main`. | `~/Dev/dlp-frontend` · github.com/Mimergt/dlp-frontend (público) |
| **DLP26** | Tema hijo de Divi 5 **en blanco** (no se le pone nada). | `~/Dev/dlp26` |
| **dlp-tiendas** | Tiendas, cobertura, horarios, mapa del checkout, pickup. | `~/Dev/dlp-tiendas` (iCloud, sin git local; remoto privado Mimergt/dlp-tiendas) |
| **dlp-panel3 / dlp-paneles** | Paneles de pedidos. | `~/Dev/dlp_funciones/` |
| Tema viejo `dlp` | Solo referencia: `docs/inventario-tema-dlp.md`, `docs/checkout-inventario.md`. Lo que sirve ya pasó al plugin. | servidor (dev) |

**Regla absoluta:** solo se toca `dev.delpuente.com.gt`. Nunca otros sitios del servidor Hostinger ni el sitio público.

## 2. Entorno y operación

- Servidor: `ssh -i ~/.ssh/id_ed25519_dlp_tiendas -p 65002 u437592302@109.106.250.100`, WP-CLI, ruta `~/domains/dev.delpuente.com.gt/public_html`, prefijo de tablas `tFdF8_`. Cloudflare delante (ignora `?ver=`).
- **Publicar dlp-frontend:** subir `Version:` en `dlp-frontend.php` **y** `DLP_FE_VERSION` (los dos), commit, `git push origin main`, luego `./scripts/release-dev.sh` (espera a GitHub raw, actualiza por WP-CLI y purga Cloudflare). GitHub tarda ~5 min en servir la versión; el script espera.
- **Publicar dlp-tiendas:** subir versión en `dlp-tiendas.php` (2 sitios) y `./scripts/deploy-dev.sh` (rsync + purga).
- Para ver cambios en el navegador: forzar recarga de assets con `fetch(url,{cache:'reload'})`.
- Interruptores de emergencia: constantes `DLP_FE_DISABLED`, `DLP_FE_DISABLED_MODULES`; en la barra de admin `?dlp_fe_off=id|all` (solo para tu vista).
- Los módulos "boot" (lógica PHP) se pausan mientras el tema viejo `dlp` esté activo; hoy está activo **DLP26**.
- Pruebas: mockups HTML locales en `preview/` (se abren con `open`); verificación en dev con el navegador emulando 375 px. **No se hacen pedidos reales de prueba**; no hay sesión de admin en el navegador (el usuario inicia sesión a mano).

## 3. Módulos del plugin (`modules/`)

| Módulo | Qué hace | Dónde |
|---|---|---|
| `app-login` | Login desde la app Android/iOS (oauth, tal cual el original; CORS limitado). **No tocar sin hablar con quien mantiene la app.** | siempre |
| `base` | CSS global. | all |
| `home-portada` | Categorías (slider Anything Slider) en estilo "pestaña curva" y en celular pestañas compactas; barra fija de categorías como **píldoras con borde**; productos como tarjeta blanca con botón + y precio negro. | home |
| `home-quickview` | Producto en modal tipo hoja inferior; enlaces directos `#producto-<slug\|id>`; total con extras; botón "Añadir" corto en celular. | home, shop, category |
| `header-menu` | Hamburguesa también en escritorio. **Falta:** guardar el módulo Menu del header en Divi (Theme Builder, layout 158753). | all |
| `menu-inferior` | Barra flotante en celular: Menú, Ubicaciones (`/restaurantes/`), Cuenta, Carrito (abre el carrito lateral con la clase `xoo-wsc-cart-trigger`, insignia con cantidad). Oculta el footer de Divi y el botón flotante del carrito en celular. No sale en checkout. | all |
| `carrito-lateral` | Estilo del panel Xootix: esquinas redondeadas, botones píldora, "Ver carro" oculto. | all |
| `splash-transicion` | Solo celular: splash con logo (palpita + barra) al abrir y entre páginas; se quita sola a los 4 s. | all |
| `checkout-estilo` | Checkout en tarjetas con íconos (Entrega, Tus datos, Factura y notas, Tu pedido, Pago); botón "Realizar pedido · total" fijo en celular; dos columnas en escritorio; botón "Seguir pidiendo"; en Pickup oculta dirección y referencia. | checkout |
| `checkout-campos-editor` | **Reemplaza** a Checkout Field Editor. Opción `dlp_fe_checkout_fields`; claves compatibles (`billing_nit`, `billing_nitname`, `billing_address_name`). Se edita en Apariencia → DLP Frontend → Campos del checkout. | siempre |
| `checkout-libreta` | **Reemplaza** a Fr Address Book. Meta de usuario `dlp_fe_addresses` (id, nombre, dirección, referencia, lat, lng), **máximo 3**; tarjetas con tienda y estado (Abierta/Cerrada); las cerradas no se pueden elegir. Usa el puente `fabfw_address_billing_id` + `window.fabfw_select_address` hacia dlp-tiendas (que pone el pin y valida). | checkout |
| `cuenta-pagina` | Mi cuenta propia: saludo + pestañas con ícono (Pedidos, Direcciones, Perfil), "Cerrar sesión" al final, pedidos en tarjetas, libreta de direcciones con mapa (hasta 3), perfil con estilo del checkout. `/mi-cuenta/` → Pedidos; sin sesión → `/ingresar/`. Endpoint de direcciones `edit-address` (antes `tsm-addresses`, corregido en el ajuste de WooCommerce). | account |
| `login-pagina` | Ingreso propio (concepto "app": fondo oscuro + hoja blanca) pintado sobre la página existente `/ingresar/` (id 4082; su contenido de Divi se conserva pero no se muestra). Entra por Ajax (`wp_signon`, límite de intentos por IP y cuenta), "Continuar como invitado" → `/`, recuperar contraseña → WooCommerce. Sin registro. `wp-login.php` redirige aquí; atajo de emergencia `wp-login.php?dlp_native=1`. | page:ingresar |
| `checkout-campos` | Quita campos (cumpleaños, CP), dirección opcional; **invitado**: correo nuevo → cuenta automática; correo existente → el pedido se asocia a esa cuenta sin sobrescribir sus datos. | siempre |
| `checkout-layout` | Selector Delivery/Pickup arriba y pagos bajo las notas. | siempre |
| `pedido-recoger` | Botón "Ya estoy aquí por mi pedido" (dlv → rtp, exige llave del pedido). | siempre |
| `plantillas-woocommerce` | Página de gracias minimalista. | siempre |
| `pedidos-meta` | `tiempo_total` y dispositivo (Android / IOS / MWeb) en cada pedido. | siempre |
| `cuenta-cliente` | Teléfono en Editar cuenta y avisos de contraseña. | siempre |

Panel de administración: Apariencia → DLP Frontend (estilo shadcn: `admin/panel.css`). Cada módulo se activa/desactiva y se acota por página; los ajustes se exponen como `var(--dlp-<modulo>-<clave>)` y `window.dlpFE["<modulo>"]`. Estructura de un módulo: `module.json` (label, description, section, where, default, css, js, php, boot, settings) + archivos.

## 4. Decisiones tomadas

- Child theme DLP26 en blanco; todo lo visual en el plugin.
- Quitar el CSS adicional de la base de datos (hecho; referencia en `docs/referencia/css-adicional-antiguo.css`).
- Estilos elegidos: categorías pestaña curva clara; productos tarjeta con botón + y precio etiqueta **negra**; modal hoja inferior; tabs de celular conceptos "1" compactos (activa grande, inactivas pequeñas); menú inferior concepto **2** (pastilla flotante, 4 botones); loader con logo; checkout concepto **1** en celular y **4** en escritorio; panel admin estilo **shadcn**.
- **Eliminado:** campo "Pedido en Restaurante" (Pickup lo reemplaza); rastreo y "Califica este pedido" (fuera por ahora). **No se toca:** la pasarela de tarjeta (EpicPay).
- Libreta de direcciones propia, desde cero, **3 direcciones máximo**; el servidor sigue revalidando cobertura y horario (dlp-tiendas).
- Datos viejos de libreta (Fr Address Book / WCMCA) **borrados en dev el 2026-10-06**; respaldo en el servidor: `~/dlp-backups/libreta-vieja-20261006-0342.sql.gz`.
- Plugins desactivados en dev: Fr Address Book, Checkout Field Editor Pro, **Theme My Login** (2026-10-06; ingreso propio verificado por el usuario). Dev tiene `disable-emails` activo a propósito: los correos (recuperar contraseña, pedidos) no salen en dev.
- Ingreso: concepto 2 elegido; **sin registro** (las cuentas se crean solas en el primer pedido); invitado va al menú.

## 5. Pendientes

**Pruebas que dependen de tiendas abiertas (desde las 11:00 hora Guatemala, tiendas cierran ~20:45)**
1. Pedido completo de punta a punta: delivery con dirección de la libreta "Abierta", pickup con "Restaurante más cercano", invitado con correo nuevo y con correo existente.
2. Con sesión iniciada: guardar una dirección en el checkout y verla en Mi cuenta; agregar con mapa desde Mi cuenta.

**Del usuario**
3. Guardar el módulo Menu del header en Divi (hamburguesa en escritorio).
4. Poner latitud/longitud reales de cada tienda (dlp-tiendas → tienda → Datos); hoy solo 3 de 16 tienen punto.
5. Probar en un celular real (modal, `#producto-…` desde WhatsApp, splash, menú inferior, compra, ingreso).
6. Probar la recuperación de contraseña en producción (en dev los correos están bloqueados).

**Páginas/áreas sin rediseñar**
7. Carrito `/carrito/` (con el carrito lateral casi no se usa: decidir si se estiliza o se redirige), página de gracias (hoy minimalista), detalle del pedido en Mi cuenta (`view-order`), recuperar/restablecer contraseña (formularios de WooCommerce), `/restaurantes/` (Ubicaciones, hecha en Divi), página de producto individual, tienda/categorías fuera del Home, 404, políticas.
8. Correos de WooCommerce con la marca (plantillas `emails/*` del tema viejo se descartaron).
9. Perfil: NIT y nombre de factura en Mi cuenta; "Pedir de nuevo" en Pedidos.

**Técnico / limpieza**
10. `dlp-tiendas`: API JS propia en lugar del puente `fabfw_*` y quitar referencias a Fr Address Book.
11. Limpiar el tema viejo `dlp` y `dlp-26-functions.php` (copias de respaldo, código muerto) y confirmar qué cambios pendientes del repo `dlp_funciones` se guardan.
12. Seguridad: revisar `wp-file-manager` y `woocommerce-legacy-rest-api`.
13. Migración a producción (no iniciada): respaldo, plan de plugins a apagar (Theme My Login, Fr Address Book, Checkout Field Editor), borrar datos viejos de libreta también allí, reglas de Cloudflare, prueba en celular.

## 6. Cosas que muerden (aprendidas)

- Divi 5 reescribe su CSS con `!important` y presets del módulo Shop: usar selectores largos.
- Anything Slider (Swiper) aísla slides: `mix-blend-mode` necesita el mismo color de barra pintado en cada slide.
- WC Product Add-Ons necesita `wc-jquery-tiptip` encolado fuera de la página de producto.
- `$_POST`/`top` como nombre de variable global JS rompe páginas ("[object Window]"): usar nombres propios.
- La opción `woocommerce_registration_generate_password` está en "no" en dev, pero `wc_create_new_customer` genera contraseña si llega vacía; el invitado con correo nuevo pasa la validación.
- El carrito lateral (Xootix): `.xoo-wsc-cart-trigger` abre el panel; la versión premium vive en `woocommerce-side-cart-premium`.
- Hora del servidor en UTC; WordPress usa la zona de Guatemala (las tiendas cierran ~20:45).
