# Memoria del proyecto DLP Frontend (Del Puente)

Documento de retoma: léelo completo al empezar una sesión nueva. Última actualización: **2026-10-06**, plugin **v0.16.0** en dev (dlp-tiendas 0.10.1).

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
| `carrito-lateral` | Estilo del panel Xootix: esquinas redondeadas, botones píldora, "Ver carro" oculto. `/carrito/` redirige a `/#carrito` y el script abre el carrito lateral. | all |
| `splash-transicion` | Solo celular: splash con logo (palpita + barra) al abrir y entre páginas; se quita sola a los 4 s. | all |
| `checkout-estilo` | Checkout en tarjetas con íconos (Entrega, Tus datos, Factura y notas, Tu pedido, Pago); botón "Realizar pedido · total" fijo en celular; dos columnas en escritorio; botón "Seguir pidiendo"; en Pickup oculta dirección y referencia. | checkout |
| `checkout-campos-editor` | **Reemplaza** a Checkout Field Editor. Opción `dlp_fe_checkout_fields`; claves compatibles (`billing_nit`, `billing_nitname`, `billing_address_name`). Se edita en Herramientas → DLP Config → Campos del checkout. | siempre |
| `checkout-libreta` | **Reemplaza** a Fr Address Book. Meta de usuario `dlp_fe_addresses` (id, nombre, dirección, referencia, lat, lng), **máximo 3**; tarjetas con tienda y estado (Abierta/Cerrada); las cerradas no se pueden elegir. Usa el puente `fabfw_address_billing_id` + `window.fabfw_select_address` hacia dlp-tiendas (que pone el pin y valida). | checkout |
| `cuenta-pagina` | Mi cuenta propia: saludo + pestañas con ícono (Pedidos, Direcciones, Perfil), "Cerrar sesión" al final, pedidos en tarjetas, libreta de direcciones con mapa (hasta 3), perfil con estilo del checkout. `/mi-cuenta/` → Pedidos; sin sesión → `/ingresar/`. Endpoint de direcciones `edit-address` (antes `tsm-addresses`, corregido en el ajuste de WooCommerce). | account |
| `login-pagina` | Ingreso propio (concepto "app": fondo oscuro + hoja blanca) pintado sobre la página existente `/ingresar/` (id 4082; su contenido de Divi se conserva pero no se muestra). Entra por Ajax (`wp_signon`, límite de intentos por IP y cuenta), "Continuar como invitado" → `/`, recuperar contraseña → WooCommerce. Sin registro. `wp-login.php` redirige aquí; atajo de emergencia `wp-login.php?dlp_native=1`. | page:ingresar |
| `checkout-campos` | Quita campos (cumpleaños, CP), dirección opcional; **invitado**: correo nuevo → cuenta automática; correo existente → el pedido se asocia a esa cuenta sin sobrescribir sus datos. | siempre |
| `checkout-layout` | Selector Delivery/Pickup arriba y pagos bajo las notas. | siempre |
| `pedido-recoger` | Botón "Ya estoy aquí por mi pedido" (dlv → rtp, exige llave del pedido). | siempre |
| `plantillas-woocommerce` | Página de gracias minimalista. | siempre |
| `pedidos-meta` | `tiempo_total` y dispositivo (Android / IOS / MWeb) en cada pedido. | siempre |
| `cuenta-cliente` | Teléfono en Editar cuenta y avisos de contraseña. | siempre |

Panel de administración: Herramientas → DLP Config (estilo shadcn: `admin/panel.css`). Cada módulo se activa/desactiva y se acota por página; los ajustes se exponen como `var(--dlp-<modulo>-<clave>)` y `window.dlpFE["<modulo>"]`. Estructura de un módulo: `module.json` (label, description, section, where, default, css, js, php, boot, settings) + archivos.

## 4. Decisiones tomadas

- Child theme DLP26 en blanco; todo lo visual en el plugin.
- Quitar el CSS adicional de la base de datos (hecho; referencia en `docs/referencia/css-adicional-antiguo.css`).
- Estilos elegidos: categorías pestaña curva clara; productos tarjeta con botón + y precio etiqueta **negra**; modal hoja inferior; tabs de celular conceptos "1" compactos (activa grande, inactivas pequeñas); menú inferior concepto **2** (pastilla flotante, 4 botones); loader con logo; checkout concepto **1** en celular y **4** en escritorio; panel admin estilo **shadcn**.
- **Eliminado:** campo "Pedido en Restaurante" (Pickup lo reemplaza); rastreo y "Califica este pedido" (fuera por ahora). **No se toca:** la pasarela de tarjeta (EpicPay).
- Libreta de direcciones propia, desde cero, **3 direcciones máximo**; el servidor sigue revalidando cobertura y horario (dlp-tiendas).
- Datos viejos de libreta (Fr Address Book / WCMCA) **borrados en dev el 2026-10-06**; respaldo en el servidor: `~/dlp-backups/libreta-vieja-20261006-0342.sql.gz`.
- Plugins desactivados en dev: Fr Address Book, Checkout Field Editor Pro, **Theme My Login** (2026-10-06; ingreso propio verificado por el usuario). Dev tiene `disable-emails` activo a propósito: los correos (recuperar contraseña, pedidos) no salen en dev.
- `/registro/` redirige (301) al inicio. No hay "Pedir de nuevo" (hay productos que solo se venden ciertos días). NIT y nombre de factura se editan en Perfil (user meta `billing_nit`/`billing_nitname`, prellenan el checkout). `/restaurantes/` la edita el usuario en Divi.
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
8. ~~Correos de WooCommerce~~ → **traspasado a dlp-tiendas** (`PLAN.md` sección 4d), junto con la **disponibilidad de productos por día**. Aquí solo queda consumir el contrato `dlp_tiendas_product_available()` para mostrar "No disponible hoy" en tarjetas y modal cuando exista.
9. Perfil: NIT y nombre de factura en Mi cuenta; "Pedir de nuevo" en Pedidos.

**Técnico / limpieza**
10. `dlp-tiendas`: API JS propia en lugar del puente `fabfw_*` y quitar referencias a Fr Address Book.
11. Limpiar el tema viejo `dlp` y `dlp-26-functions.php` (copias de respaldo, código muerto) y confirmar qué cambios pendientes del repo `dlp_funciones` se guardan.
12. Seguridad: revisar `wp-file-manager` y `woocommerce-legacy-rest-api`.
13. Migración a producción (no iniciada): respaldo, plan de plugins a apagar (Theme My Login, Fr Address Book, Checkout Field Editor), borrar datos viejos de libreta también allí, reglas de Cloudflare, prueba en celular.

## 5b. Pruebas automatizadas de pedidos (2026-10-06, dev, tiendas abiertas)
Arnés en `scripts/pruebas/` (README). 14 pedidos de prueba, todos **cancelados**. Cubierto y correcto: invitado (correo nuevo → cuenta automática; correo existente → pedido a esa cuenta sin sobrescribir sus datos), pickup (tienda + hora; rechazos), delivery (cobertura, punto, datos faltantes, mínimo Q60), libreta con sesión (máx. 3; la 4.ª no se guarda; dirección guardada; prellenado), extras obligatorios y comentarios, dispositivo (MWeb), tienda/NIT/notas en el pedido. Bug hallado y corregido: en Pickup el servidor exigía dirección y referencia (v0.15.5). Nota: en la configuración guardada del editor, `billing_address_2` y `billing_address_name` están como **obligatorios**. Un fallo aislado (página de error en un pedido) no se repitió en 4 intentos.
Faltan: pedidos con extras de varios días de disponibilidad, cupones, tarjeta (no se toca), y todo lo que depende de correos (bloqueados en dev).

## 6. Cosas que muerden (aprendidas)

- Divi 5 reescribe su CSS con `!important` y presets del módulo Shop: usar selectores largos.
- Anything Slider (Swiper) aísla slides: `mix-blend-mode` necesita el mismo color de barra pintado en cada slide.
- WC Product Add-Ons necesita `wc-jquery-tiptip` encolado fuera de la página de producto.
- `$_POST`/`top` como nombre de variable global JS rompe páginas ("[object Window]"): usar nombres propios.
- La opción `woocommerce_registration_generate_password` está en "no" en dev, pero `wc_create_new_customer` genera contraseña si llega vacía; el invitado con correo nuevo pasa la validación.
- El carrito lateral (Xootix): `.xoo-wsc-cart-trigger` abre el panel; la versión premium vive en `woocommerce-side-cart-premium`.
- Hora del servidor en UTC; WordPress usa la zona de Guatemala (las tiendas cierran ~20:45).


## Página de gracias (0.17.x) — módulo `gracias-pagina`
- Plantilla `plantillas-woocommerce/templates/checkout/thankyou.php` llama a `dlp_fe_gr_render()`; diseño: negro arriba con hamburguesa SVG que se arma (5 s), hoja blanca con barra de 4 estados con íconos animados, total/pago, «Ver mi pedido» (solo si la cuenta es la dueña), «Seguir pidiendo», detalle plegado.
- Estados: pending/on-hold=Recibido, processing=Preparando, dlv/rtp=En camino/Listo, completed=Entregado. Refresco: ajax `dlp_fe_gr_status` (exige order_key) cada 20 s, recarga si cambia. Sin rastreo ni tiempo estimado inventado (solo `woofood_time_to_pickup` si existe).
- «Ya estoy aquí» sale de `dlp_fe_recoger_button()` (pedido-recoger); filtro `dlp_fe_recoger_en_gracias` apaga su caja gris.
- Prueba: `chk.py` ahora devuelve `url` del pedido; un pedido de invitado abre bien con la misma sesión (el navegador sin sesión pide login porque la cuenta se crea sola). Pedidos de prueba 158807–158809 cancelados.
- Pendiente: probar estados dlv/rtp en pickup cuando esos estados existan en dev (el plugin WooFood no los registra ahí).

## Tema claro/oscuro y extras en grupo (0.18–0.20)
- Módulo `tema` (pestaña «Tema oscuro» del panel): variables `--dlp-*` claras y oscuras; `dark.css` lleva solo reglas oscuras y `module.php` les antepone la condición (teléfono oscuro o `data-dlp-tema="oscuro"`). API `dlpTema.set('claro'|'oscuro'|'auto')`; sin botón para el cliente todavía (sugerido en Mi cuenta → Perfil). Migrado: home, vista rápida, checkout, libreta, Mi cuenta, login, gracias, menú inferior, carrito lateral, páginas sueltas.
- Módulo `paginas-extra`: recuperar contraseña, 404 propia, detalle del pedido (plantilla `myaccount/view-order.php`), redirecciones 301: producto → `/#producto-slug` (abre el modal), categorías/tienda → inicio (`?dlp_native=1` las muestra).
- Extras en grupo con total exacto (`extras-grupos` + `home-quickview`): extras tipo «Cantidad» con `[grupo:Título=N]` en la descripción forman un grupo de total exacto N. Vista rápida: acordeón cerrado con contador y resumen; botón Añadir bloqueado hasta completar; el servidor valida (campo `addon-<field_name>`). Aplicado en dev solo a Trío de Clásicas (126123) con `scripts/extras-bebidas.php <id>` (9 bebidas, las 6 no-Frezka +Q5 por combo; copia en meta `_product_addons_backup_bebida`). Pruebas: con 2 y 4 bebidas se rechaza; con 3 se agrega y el carrito muestra «Frezka Tamarindo: 2, Coca Cola: 1».
- Pendiente: aplicar el grupo a los demás combos; ver el detalle del pedido y Mi cuenta en oscuro con sesión real.

## Nuevo Home y página /menu/ (21 oct 2026)
- Proyecto aprobado; salida en 2 semanas. Home nuevo con la estructura del sitio de referencia (joseluist18.sg-host.com): slide → promociones en slide → categorías («Elige el nivel de tu hambre») → más pedidos → footer actual. Tipografía de ese sitio: Helvetica Neue (Roman/Medium/Bold; títulos en Condensed Bold, mayúsculas); rojo #ED1C24, negro #231F20. Fuentes en `preview/fonts/` (NO se suben al repo público: licencia).
- Mockup con 3 opciones: `preview/home-nuevo.html` (A fiel a la referencia, B oscura tipo app, C editorial). Falta elegir.
- Página `/menu/` (id 158945, duplicado del home de Divi): los módulos del home (`home-portada`, `home-quickview`) ahora aplican también a `page:menu` (0.21.0). El botón «Menú» del menú inferior lleva a /menu/ con ícono de hamburguesa; producto y categorías redirigen a /menu/ (`#producto-slug` abre el modal). Cuando el nuevo home esté listo, quitar `home` del alcance de esos módulos.
- `/tienda/` redirige a `/` por una regla del plugin Redirection (no es nuestra).

## Home nuevo en producción de dev (0.22–0.23)
- Módulo `home-nuevo`: códigos cortos para Divi (módulo Texto/Código): `[dlp_banner]`, `[dlp_promos]`, `[dlp_categorias]`, `[dlp_mas_pedidos]`, `[dlp_home]` (todo junto). Atributos opcionales: `[dlp_promos categoria="combos" max="6" titulo=".." rotulo=".."]`, `[dlp_mas_pedidos ids="2055,2049,2053,2057"]`, `[dlp_categorias lista="slug|Título|URL/slug2|Título2"]`. Textos, banners (4), categorías (slug | título | foto), IDs de más pedidos, fuentes y enlaces: panel DLP Frontend → Home / Portada.
- La página Home (id 12282) tiene 4 secciones Divi (Banner, Promociones, Categorías, Más pedidos) con un código cada una; copia del contenido anterior en el meta `_dlp_home_backup`.
- Tarjetas de promos = mismo marcado que el menú (`ul.products li.product`), así se ven iguales y abren la vista rápida; más pedidos son filas que abren el modal con `#producto-slug`. Banner: a todo el ancho y sin redondeo en escritorio; redondeado en celular. Home siempre claro (variables del Tema restablecidas en `.dlph`).
- Fuentes: `fuente_titulos` y `fuente_texto` (URL del archivo en Medios) → @font-face; sin URL usa Helvetica Neue del sistema (Apple) o Arial Narrow/Impact. Licencia pendiente.
- Fotos de prueba del sitio de referencia importadas a Medios (IDs 158947–158953).
- Categorías enlazan a `/menu/#categoria-<slug>` (el script activa la pestaña). Pendiente verificar ese salto con sesión real y quitar `home` del alcance de `home-portada`/`home-quickview` cuando ya no hagan falta en el inicio (la vista rápida sí hace falta).
- (0.24.0) Slides del Home infinitos y automáticos con ritmos distintos: banner `autoplay` (def. 4 s; en dev está en 5), promos `autoplay_promos` 6 s, categorías en celular `autoplay_cats` 8 s; pausan con mouse/toque/pestaña oculta y respetan «reducir movimiento». Técnica: clones a ambos lados + salto invisible al quedar quieto el scroll. Categorías: se quitó «Promos» (ya está arriba) y en escritorio un cuadro rojo «menú completo» rellena el hueco de la última fila; se quitó el botón negro del final; en celular la última sección deja 120 px para el menú inferior.
- (0.25.x) Banner con imagen propia para celular (`bannerN_img_movil`, `<picture>`); sin ella en celular se recorta a 2:1 (centro). Espacios entre secciones reducidos; el margen inferior de 120 px (menú inferior) solo va en la última sección. Panel: los módulos pueden agrupar ajustes con `"group"` en module.json y se muestran como pestañas (más una «Avanzado» con «Dónde se carga»); la pestaña activa se recuerda por sesión. `scripts/purge-dev.sh` ahora también purga `admin/` (antes el CSS del panel quedaba en caché de Cloudflare).
- (0.26.0) Banners con proporción fija ≈2.4:1 (`aspect-ratio` + cover): ancho completo visible también en celular. Medida recomendada para pedir banners: **1792×750 px** (≈2.4:1), texto y producto dentro de la franja central (se puede recortar ~10 % arriba y abajo si la imagen es más alta, p. ej. 2000×669 se recorta un poco). Banners de prueba subidos a Medios (IDs 158963–158965). Nuevo `[dlp_footer]`: franja oscura a todo el ancho con fotos que cambian solas con fundido (sin controles), título, botón y teléfono opcional; ajustes en la pestaña «Footer» del panel; añadido como 5.ª sección de la página Home (12282). Fotos de prueba: DP_XELA (IDs 158966–158968).
- (0.26.1) Banner más bajo y sin recortes: caja 3:1 con `object-fit: contain` y fondo negro (los banners 1792×750 quedan con barras negras laterales casi invisibles porque su fondo es negro); altura máxima en pantallas anchas = ajuste «Altura máxima del banner» (def. 420 px); ajuste «Ajuste de la imagen» (completo / recortar). Celular: 347×116 px.
- (0.26.2–0.26.3) Banner: cada imagen se muestra completa con su proporción original (sin caja fija ni recorte); ancho máximo configurable («Ancho máximo del banner», def. 1440 px; lados en negro). **Jetpack (CDN de imágenes i0.wp.com) reducía los banners a 1080 px de ancho** (por eso se veían borrosos): ahora los `<img class="dlph-bimg">` se excluyen con el filtro `jetpack_photon_skip_image` y se sirve el original. Si los banners tienen proporciones distintas, la más alta marca la altura y las demás quedan centradas sobre negro: conviene pedir todos con la misma proporción (p. ej. la de portada de Facebook, 820×312 ≈ 2.63:1, o 1792×750).
- (0.26.4) Banner sin barras: la pista toma el alto de la imagen visible (script `fit()` con transición de 0.35 s) y en escritorio el banner es un bloque centrado de hasta 1440 px (`Ancho máximo del banner`) sobre fondo blanco, sin barras negras laterales ni arriba/abajo. En celular: bloque con esquinas redondeadas, ancho completo menos 14 px por lado. Pendiente: «falta algo» en celular (opciones: sin márgenes laterales, arte propio más alto, botón/texto encima).
- (0.26.5) Banner de escritorio de borde a borde (100vw, sin tope ni barras); se quitó el ajuste «Ancho máximo». Solo 2 banners activos (los de 1792×750, IDs 158964–158965); el de 2000×669 (158963) quedó en Medios sin usar. Con 1792×750 a 1920 px de pantalla el banner mide ~804 px de alto: para bajarlo hace falta arte más ancho y bajo (3:1 o 4:1).
- (0.26.6) Banner escritorio: ancho completo y alto FIJO configurable («Altura del banner en escritorio», def. 600 px; la imagen se ajusta al ancho y se recorta arriba/abajo con object-fit cover centrado). Celular intacto (completo, 347×145, esquinas redondeadas). Para que el recorte no se note: arte de escritorio ≈3.2:1 (1920×600).
- (0.26.7) Cuadro rojo «Ver menú completo» (solo escritorio) con el ícono de hamburguesa del menú inferior.

## Plan próximo (acordado 22 oct 2026)
- Salida a producción en ~2 semanas (aprobado el 21 oct).
- Mañana: productos y extras (aplicar el grupo «elige 3 bebidas» a los demás combos con `scripts/extras-bebidas.php`, revisar extras tipo desplegable, etc.).
- Pasado mañana: tiendas (si llega la información: coordenadas/puntos de las 16 tiendas, horarios).
- Pendientes abiertos: celular del banner («falta algo»), tipografía Helvetica Neue (licencia/archivos), banners finales 3.2:1 (1920×600), fotos propias, Mi cuenta/detalle del pedido en oscuro con sesión real, emails y disponibilidad por día (dlp-tiendas).
- (0.27.0–0.27.2) Banner reescrito como slide SIMPLE con fundido (sin scroll, sin clones, sin loop): una imagen a la vez, puntos + flechas debajo, avance solo (`autoplay`), pausa con mouse/dedo y deslizar con el dedo. Escritorio: ancho completo; el alto sigue la proporción de la primera imagen (`--dlph-r`, calculada en PHP desde la metadata del adjunto) y «Altura máxima del banner» (def. 600; en dev 500) es el tope: solo si la pantalla es tan ancha que se pasa, se recorta centrado. Celular: imagen completa con esquinas redondeadas (sin tocar). Banners actuales en dev subidos por el usuario: 1792×550 (3.26:1). El «scroll en sí mismo» venía del track de scroll horizontal con la imagen más alta que la caja.
- Tip de proceso: cada release espera a la caché de raw.githubusercontent (hasta ~5 min); juntar varios cambios por release.

## Acordeón para extras de casillas (0.28.0)
- Convención: en la descripción de un add-on de casillas (checkbox) del plugin de extras, agregar `[acordeon:Título]`. Los add-ons CONSECUTIVOS con el mismo título se envuelven en un solo acordeón cerrado (reusa `.dlpqv-grp`/`.dlpqv-grp-box`/`grpToggle`; el marcador se quita del texto y el resto de la descripción se conserva).
- Cabecera con el título y un resumen de lo marcado («Carnuda 1: Sin Tomate, Sin Cebolla · Carnuda 2: Sin Salsa» / «Toca para elegir»). Sin contador y sin bloquear «Añadir» (son opcionales). Los campos siguen en el formulario aunque el acordeón esté cerrado; no toca precios ni total.
- Probado en dev con Carnuda lovers (ID 143735; marcador agregado a las dos descripciones, copia previa en el meta `_product_addons_backup_acordeon`): cerrado al inicio, resumen correcto, enviado con el acordeón cerrado y el carrito/checkout muestra «Carnuda 1: quitar ingredientes: Sin Tomate / Sin Cebolla», «Carnuda 2: …: Sin Salsa».
- Para los ~28 productos restantes: pegar ` [acordeon:Quitar ingredientes]` al final de la descripción de cada add-on de casillas y activar «Mostrar descripción».
- (0.28.1) Acordeón de casillas con UN solo extra: el resumen muestra directo lo marcado («Sin Tomate, Sin Tocino») y se oculta el título interno (`dlpqv-grp-box--solo`).
- **Ingredientes removibles aplicados en dev** con `scripts/ingredientes-removibles.php` (datos de INGREDIENTES REMOVIBLES.xlsx; idempotente; copia en meta `_product_addons_backup_ingredientes`): 18 productos / 33 extras. Una hamburguesa = un extra «Ingredientes» (casillas «Sin X») con `[acordeon:Quitar ingredientes]`; promos de varias hamburguesas = un extra por hamburguesa («Del Puente 1…», «Clásica 1…», como Carnuda lovers). IDs: individuales 2131, 2139, 2135, 2137, 124437, 124441, 2141; combos 2049, 2057, 2053, 2055, 124430, 124435, 2059; promos 122472 (Felices: 2 Del Puente + 2 Clásica), 126123 (Trío Clásicas ×3), 126125 (Trío Del Puente ×3), 143397 (Las Favoritas: Clásica, Del Puente, Carnuda, Tejana; los tres últimos están en borrador).
- **Sin aplicar (no existen en dev):** Hamburguesa y Combo La Carnuda, Smash ×6 (La Legendaria, La Clásica, Jalapeño Popper; individual y combo), Hamburguesa de Pollo y Combo Hamburguesa de pollo (en dev hay 4 de pollo distintos: Ranch, Chipotle, Honey Mustard, Tenders). Cuando existan: agregar su ID y lista al arreglo `$P` del script y volver a correrlo.
- Supuestos a confirmar: en el Excel Felices Los 4 y Las Favoritas traen una sola lista (la de Felices sin «Salsa»; la de Las Favoritas es solo la de la Tejana); se usaron las listas de cada hamburguesa individual del mismo archivo.
- (23 oct) «Curly Fries» → «Fries» en los complementos: script `scripts/renombrar-curly-fries.php` (aplicado en dev a 143735, 143397, 122472; copia en `_product_addons_backup_fries`). Quedan textos con «Curly Fries» en títulos/descripciones (45 posts, casi todos borradores viejos; publicado: 2171 «2 libras de Curly Fries»; borradores en reconstrucción: 126123, 126125) sin tocar. Los productos de promos están en borrador porque se recrean uno a uno según producción.

## Paridad de catálogo dev ↔ producción (23 oct 2026)
- Fuente: API pública `wp-json/wp/v2/product` + páginas de producto de delpuente.com.gt (solo lectura). Producción: 87 productos publicados (mismos IDs que dev, que es un clon).
- Dev: 71 publicados iguales · 8 en borrador (promos: 143397, 126125, 126123, 122461, 122470, 122458, 122456, 108988) · 8 faltaban y se crearon (IDs 158996–159010: 6 Smash + 2 Tenders infantiles, con foto, categorías y descripción de producción; mismos slugs que producción, algunos engañosos: `la-carnuda-copia` = Combo Clásica Smash, `jalapeno-popper-smash` = Combo Legendaria Smash) · 2 publicados en dev que no están en producción: Cerveza Gallo (2227) y BBQ Crunch (146577).
- Precios: 27 productos tenían precio distinto; se alinearon con producción (valor anterior en meta `_price_backup_sync`). Las descripciones de los dos Tenders infantiles en producción parecen copiadas de Smash (salsa Legendaria/jalea de frambuesa): se replicaron tal cual.
- Pendiente: extras de los 8 nuevos y de las promos en borrador (idea: leer los extras de cada producto desde su página pública de producción y convertirlos en extras de dev).
- (23 oct) Cerveza Gallo (2227) y BBQ Crunch (146577) pasaron a borrador en dev (no están en producción).
- **Extras de promos y productos nuevos generados desde producción**: producción usa WooFood (categorías de extras con título, obligatoria, radio/casilla y opciones con precio, legibles en el HTML de la página pública). `scripts/extras-promos.php` (dev; REEMPLAZA los extras; copia en `_product_addons_backup_promos`; fija `_product_addons_exclude_global`=1) aplicó: promos 143397 (Las Favoritas), 126125, 126123, 122461, 122470, 122458, 122456 (sin extras), 108988 y nuevos 158996, 158998, 159006 (combos Smash), 159000, 159002, 159004 (individuales Smash), 159008 (sin extras), 159010 (combo Tenders infantil). Estructura: carne favorita (Premium / «Si, N Tortas Angus» +12 c/u) → complemento (N Fries / N Cheese poppers +10 c/u / N Aros +10 c/u; combos sueltos: Papas fritas / Cheese poppers +10 / Aros +10) → bebidas (promos: grupo exacto de N con 9 bebidas, no-Frezka +5 c/u; combos sueltos: lista desplegable de 9) → un acordeón de ingredientes por hamburguesa. Probado en dev: Combo Clásica Smash (bebida Coca Cola +5, poppers +10 → Q70, el carrito muestra todo). Las promos siguen en borrador (la vista rápida no abre borradores).
- Decisiones a confirmar: Las Favoritas (producción solo tenía «bebida extra?» y «carne (box) 6 tortas +72»; se armó como 4 combos: carne 4 tortas +48, complemento ×4, 4 bebidas, 4 acordeones); complemento de promos +10 por unidad (producción Felices: 4 poppers +40); «1 Papas Fritas + 1 Bebida» recibió elección de bebida (producción no tenía extras); «Papas Fritas X 3» y Tenders infantil individual sin extras.
- **Confirmado por el usuario (23 oct):** Las Favoritas como se armó; complementos como en producción (si piden cambios, se ajustan); bebida agregada en «1 Papas Fritas + 1 Bebida» está bien; Papas X 3 y Tenders infantil individual sin extras. Pendiente: decidir cuándo publicar las 8 promos (siguen en borrador) y a cuáles de los 4 pollos de dev aplica «Hamburguesa de Pollo / Combo Hamburguesa de pollo» del Excel de ingredientes (Pepinillos, Lechuga, Salsa).
- (23 oct) Las 8 promos (143397, 126125, 126123, 122461, 122470, 122458, 122456, 108988) publicadas en dev y revisadas en la vista rápida a 375 px: Trío de Clásicas probado de punta a punta (Angus +36, poppers +30, 2 Tamarindo + 1 Coca Cola +5 = Q226; el carrito muestra todo). La categoría promos tiene 13 publicados; el Home muestra los primeros 8 por `menu_order` (los 3 combos Smash, con menu_order 0, salen primero): subir «máximo de productos» o reordenar si se quiere otro orden.
- (23 oct) «La Clásica + Frezca» (122470) ahora pide elegir bebida (lista de 9 como La Carnuda; no-Frezka +Q5); probado: Angus +12 + Tiky +5 → Q63 y el carrito lo muestra. `scripts/extras-promos.php` acepta IDs como argumentos para procesar solo esos productos. Felices los 4: el usuario ajusta él mismo el precio de «4 Tortas Angus» (Q48).

## Combos de res (23 oct 2026)
- Producción publica 12 en la pestaña `#combos-de-res` (Clásica, Del Puente, Tejana, Torito, Bacon Cheddar, Infantil, Swiss Mushroom, Derretido, 3 Smash, La Carnuda); todos existen ya en dev. Categorías comparadas para los 87 productos: única diferencia = Las Favoritas (143397) estaba también en `combos` en dev (en producción solo promos y para-compartir): quitada.
- Orden: se reprodujo el orden global de producción (fusión de las 8 pestañas del home, consistente) en `menu_order` de 72 productos (10, 20, …); copia del valor anterior en el meta `_menu_order_backup`. Los productos pueden estar en varias categorías (no se duplicó ninguno).
- Extras de los combos clásicos con `scripts/extras-promos.php` (carne favorita si aplica → bebida → complemento → ingredientes; sin extras globales): 2057, 2049, 2055, 2053, 2059 (con carne), 124430, 124435, 2061 (sin carne; Derretido sin ingredientes). Probado: Del Puente con Angus +12, Coca Cola +5, Cheese poppers +10, sin Tocino = Q92; Derretido con Aros +10 = Q46; el carrito muestra todo.

## Combos de pollo (23 oct 2026)
- Producción (`#combos-de-pollo`, 6): Honey Mustard 101944, Ranch 101940, Tenders infantil individual (dev 159008), Chipotle 101954, Chicken Tenders 101958, Combo Tenders infantil (dev 159010). En dev ya estaban los 6 con mismo orden, categoría (`opciones-pollo`) y precio.
- Extras (`scripts/extras-promos.php`): bebida (9) + complemento + acordeón de ingredientes «Pepinillos, Lechuga, Salsa» (lista «Hamburguesa de Pollo» del Excel) en los 3 de pechuga (Honey Mustard, Ranch, Chipotle); Chicken Tenders sin ingredientes; Tenders infantil (ya hecho). Probado: Honey Mustard con Orange Crush +5, Aros +10, sin Pepinillos = Q75; Tenders = Q55.
- Pendiente: aplicar la misma lista de pollo a los 3 individuales de pechuga (101946 Honey Mustard, 101942 Ranch, 101950 Chipotle) al llegar a la pestaña «Hamburguesas individuales».

## Hamburguesas individuales (23 oct 2026)
- Producción (`#hamburguesas-individuales`, 15): Del Puente 2131, Clásica Smash 159000, Jalapeño Popper Smash 159002, La Clásica 2139, Legendaria Smash 159004, La Tejana 2137, La Torito 2135, Bacon Cheddar 124437, Swiss Mushroom 124441, Infantil 2141, Derretido 2143, Chicken Honey Mustard 101946, Chicken Ranch 101942, Chicken Chipotle 101950, Chicken Tenders 101960 (ids de dev). Los 15 ya existían en dev con mismo orden, categoría y precio.
- Extras: carne favorita (Premium / «Si, 1 Torta Angus» +12) en Del Puente, Clásica, Torito, Tejana, Infantil, Jalapeño Popper Smash y Legendaria Smash (como producción) + acordeón de ingredientes en todas salvo Derretido y Chicken Tenders (sin extras). Pollo de pechuga ×3 con «Pepinillos, Lechuga, Salsa». Probado: Tejana con Angus +12 = Q65 (carrito: «Ingredientes: Sin Jalapeños / Sin BBQ»); Chicken Ranch sin Salsa = Q53; Derretido y Tenders abren sin extras.
- (23 oct) Hamburguesas individuales (los 15 ids de dev: 2131, 159000, 159002, 2139, 159004, 2137, 2135, 124437, 124441, 2141, 2143, 101946, 101942, 101950, 101960) con dos extras OPCIONALES al final: «Añade un complemento» (productos publicados de la categoría `sides`: Cheesy Poppers Q20, Aros de cebolla Q20, Papas individuales Q15) y «Añade una bebida» (los 9 publicados de `bebidas`, Q9–Q17), con nombres y precios del catálogo. `scripts/anade-complemento-bebida.php` (idempotente; copia en `_product_addons_backup_anade`; si cambia el catálogo se vuelve a correr). Probado: La Clásica individual + Papas individuales +15 + Coca Cola +11 = Q74.

## Para compartir (23 oct 2026) y regla `[elegir:min-max]` (0.29.0)
- Producción `#para-compartir` (10): Felices, Trío Clásicas, Trío Del Puente, Las Favoritas (promos ya hechas) + Tender Box 24 (134490), Tender Box 14 (134482), Box Del Puente 2068, Box Clásica 2145, Box Tejana 2147, Box Mixto 2149. En dev ya estaban todos (orden, categoría, precio iguales).
- Extras (`scripts/extras-promos.php`): Box = carne favorita (Premium / «Si, 6 Tortas Angus» +72) + «Añade bebida extra» opcional (6 Frezkas Jamaica / Tamarindo / mixtas, +65). Tender Box = «Elige tus aderezos» (Honey Mustard, Ranch, Búfalo): 14 → exactamente 2; 24 → entre 2 y 3 (producción: mín 2, máx 3 aunque el título diga 3). Los Box no llevan acordeón de ingredientes (no están en el Excel).
- **Nueva convención**: `[elegir:N]` o `[elegir:min-max]` en la descripción de un extra de casillas → contador «Elige 2 · Marcadas: n», bloquea las casillas sobrantes al llegar al máximo, el botón Añadir espera al mínimo (vista rápida) y validación en servidor (`extras-grupos`). El plugin de extras NO hace cumplir min/max de casillas por sí solo (se comprobó: dejaba añadir 1 aderezo). Probado: Tender Box 14 — con 0/1/3 aderezos el servidor rechaza, con 2 agrega.
- (23 oct) Tender Box: los aderezos pasan a **cantidad** (se pueden repetir): grupo «Elige tus aderezos (N)» con Honey Mustard / Ranch / Búfalo; 24 unidades = 3 en total, 14 unidades = 2 en total (exactos). Probado: 1 Ranch + 2 Búfalo → carrito «Ranch: 1 Búfalo: 2». Box: «Añade bebida extra» ahora incluye «6 Frezkas Sandía Limón & Maracuyá» (+65). La regla `[elegir:min-max]` queda disponible para otras casillas con mínimo/máximo.

## Sides (23 oct 2026)
- Producción `#sides` (3, sin extras): Cheesy Poppers 2233 (Q20), Aros de cebolla 2235 (Q20), Papas individuales 117093 (Q15). Dev idéntico (mismo orden, precio, categoría, foto, sin extras): no hubo cambios. En la categoría `sides` de dev hay 7 productos más pero en BORRADOR (Jalapeño Poppers, Onion Rings, Curly Fries, Cheesy Fries, Chicken Fries, Jalapeño Bacon Fries, Pétalos de Cebolla): no están en producción. Probado: los 3 se agregan al carrito sin extras.

## Para hacer en casa (22 oct)
- Producción: 35 productos en la categoría «Do it yourself» (27 publicados, 8 en borrador: 2199, 2579, 2634, 2972, 2974 oculto, 2976, 2978, 3002); ninguno lleva extras. Dev tiene los mismos 35.
- Orden de dev ajustado a la columna Posición del CSV de producción: kits, pan, torta, papas, poppers, queso, tocino, cebolla, champiñones, vegetales, salsas, sobrecitos (menu_order 610…870); borradores al final (880…960).
- Solo datos (wp eval), sin cambios de plugin.
- Límite de productos por pestaña: cada pestaña del menú es un layout Divi (120361, 1833, 138171, 1964, 1977, 1985, 2002, 2011) con un módulo Tienda sin «número de productos» (Divi usa 12). Se fijó `postsNumber` = 50 en los 8 (backup en meta `_dlp_backup_posts`). Para producción hay que repetirlo.
- Kits: «Añade tus bebidas» = 9 extras de cantidad (sin tope) agrupados con la nueva marca `[grupo:Título=*]` (grupo libre: acordeón con contador «N elegidas», sin total exacto ni bloqueo del botón). 0.29.2.
- Grupos con total exacto (`[grupo:Título=N]`, ej. «Felices los 4»): etiqueta roja «Obligatorio» junto al contador «0 de N» (0.29.4). Los grupos libres `=*` no la llevan (son opcionales).
- Checkout: NIT vacío → «C/F» y nombre de factura vacío → «Consumidor Final», en el servidor (módulo `checkout-campos`, 0.29.5). Sustituye el JS «nit22» de Custom CSS & JS.
- Checkout: además de `_billing_nit`/`_billing_nitname`/`_billing_address_name` (WooCommerce) se guardan SIN guion bajo, que es lo que lee el panel (`rest.php`, `history.php`) y lo que dejaba el plugin viejo de campos (0.29.6). Sin esto el panel mostraba NIT vacío en pedidos nuevos.
- Ingredientes removibles en orden de receta (feedback del cliente, hoja «detalle orden» de INGREDIENTES REMOVIBLES (1).xlsx): `scripts/ingredientes-orden-receta.php` reordena 37 extras «quitar ingredientes» (Individuales, Combos, Promos, Smash, Pollo, Carnuda Lovers) y usa los nombres de esa hoja (Salsa de la casa, Salsa BBQ, Huevo estrellado, Salsa Ajo Cilantro). Backup en `_product_addons_backup_orden_receta`. Felices los 4 / Tríos / Favoritas siguen con un extra por hamburguesa (cada uno en el orden de su receta).
- Textos (feedback cliente 23 oct): «Curly Fries» → «Fries» en nombre y descripciones de todos los productos; «bebida» → «Frezka(s)» en las descripciones de los productos publicados (`scripts/textos-fries-frezka.php`, copia en `_dlp_textos_backup_fries`). Carnuda Lovers = 2 Carnudas + 2 complementos (extras «Complemento 1» y «Complemento 2»). Etiqueta «Obligatorio» de los grupos ahora va DESPUÉS del contador «0 de N» (0.29.7).
- «Frezca» → «Frezka» en el nombre de 'La Clásica + Frezka' (id 122470; el slug se dejó igual para no romper enlaces) y en un borrador. Carnuda Lovers: se deja con Complemento 1 y 2.
- Checkout (0.29.8, módulo `checkout-estilo`): el cupón ahora se escribe en una **ventana emergente** (el enlace «¿Tienes un cupón?» la abre; se aplica con el mismo endpoint de WooCommerce y el resultado —error o éxito— se ve dentro de la ventana; Esc/clic afuera/× cierran). Avisos y errores rediseñados: tarjeta clara con ícono y barra de color (rojo error, verde éxito, gris info), texto oscuro de 14 px; cada error con campo se puede tocar («· Corregir») para ir al campo; los campos con error se marcan en rojo. Con tema oscuro en `dark.css`.
- Feedback cliente (0.29.10): «Fries» → «Papas fritas» en nombres, descripciones y extras (`scripts/papas-fritas-y-complementos.php`); en Felices los 4, Trío de Clásicas, Trío Del Puente y Las Favoritas el acompañamiento pasa de un desplegable («4 Fries / 4 Cheese poppers / 4 Aros») a un grupo `[grupo:Elige tus N complementos=N]` para repartirlo (p. ej. 1 papa + 3 aros): Papas fritas incluida, Cheese poppers y Aros +Q10 c/u (respaldo `_product_addons_backup_complementos`). «Toca para elegir» reemplaza el «Elige una opción…» de los desplegables (filtro gettext en `extras-grupos`). ATENCIÓN: `DLP_FE_VERSION` (define en dlp-frontend.php) debe subirse junto con `Version:`; se había quedado en 0.29.1 (sirve de `?ver=` de CSS/JS).


## 0.30.0 — Menú y antifraude
- El panel pasó de Apariencia a **Herramientas → DLP Config** (slug `dlp-frontend`, URL `tools.php?page=dlp-frontend`). El plugin ya no es solo front-end.
- Módulo `antifraude` (boot): lista de bloqueos por correo / teléfono (últimos 8 dígitos) / cuenta / IP (opcional), también para invitados. Opciones `dlp_fe_af_bloqueos` y `dlp_fe_af_eventos`.
  - Rechaza en `woocommerce_after_checkout_validation` (no se crea el pedido) y quita pasarelas en `order-pay`.
  - Regla tarjeta: N fallos (`woocommerce_order_status_failed`, no `cod`) en la ventana → bloqueo temporal. Defecto 3 en 60 min, 24 h.
  - Regla pedidos seguidos: segundo pedido en < 60 s → rechazo + bloqueo de 30 min. Un pedido fallido no cuenta como reciente.
  - API: `dlp_fe_af_block/unblock/order_blocked/order_set`. dlp-panel3 1.14.0 la usa en «Bloquear cliente» (cae a `is_active` de User Blocker si el módulo no está). Bloqueo permanente de cuenta también marca `is_active=n`.
  - Límite: errores de validación de tarjeta del plugin (número inválido) no crean pedido, no cuentan.

## 0.31.0 — Menú único con submenús
- Menú de primer nivel **DLP Config** (`admin.php?page=dlp-config[-seccion]`) con 6 submenús: Inicio y catálogo, Checkout y pedidos, Cuenta y acceso, Apariencia, Antifraude, Sistema (ver `DLP_FE_Registry::sections()`).
- Cada módulo declara en su `module.json`: `section`, `tab` (nombre corto) y `order`. Una pestaña por módulo; se guarda solo el módulo de la pestaña abierta. Pestañas especiales: Editor de campos (módulo `checkout-campos-editor`) y Bloqueos y actividad (antifraude).
- URL helper: `DLP_FE_Admin::url($seccion, $pestaña)`. Un módulo nuevo sin `section` cae en Sistema.

## 0.32.0 — Disponibilidad de productos
- Módulo `disponibilidad`. Metas de producto: `_dlp_dias` (0=domingo…6=sábado; ausente = todos) y `_dlp_tiendas_excluidas` (IDs `extra_store`).
- Días: el producto sale de las listas (`pre_get_posts`), no es comprable y la página muestra «Disponible solo: …». Se queda publicado. Caché `dlp_fe_disp_prog` (se limpia al guardar el producto). Día según `wp_date` (zona del sitio).
- Tiendas: se valida en `woocommerce_after_checkout_validation` (prioridad 25) usando `dlp_tiendas_resolve_checkout()`; la tienda solo se conoce en el checkout (antes no hay ubicación), por eso no se oculta del menú.
- UI: caja «Disponibilidad (DLP)» en el producto, columna en la lista de productos y DLP Config → Inicio y catálogo → «Disponibilidad por tienda» (marcar/desmarcar productos de una tienda).

## 0.33.x — Todo el front en español
- Barrido de textos en inglés (páginas públicas, checkout con errores, carrito lateral, vista rápida, Mi cuenta, gracias, login). Solo había: pasarela de tarjeta (SkyVerge/Cybersource), selector Delivery/Pickup (dlp-tiendas), menú «Home», título de la página duplicada «Checkout» (ID 13) y tooltips.
- Módulo `traducciones`: mapa exacto inglés→español por `gettext` (dominios `woocommerce-plugin-framework` y `woocommerce-gateway-cybersource`) con formulario, validaciones, rechazos de tarjeta y Mi cuenta → Tarjetas. Para sumar un texto: añadir al arreglo de `modules/traducciones/module.php`. Su `script.js` traduce tooltips (`required`, iframes de pago).
- **Delivery / Pickup se quedan en inglés** (decisión del cliente: así los identifican siempre). Se probó traducirlos y se revirtió en dlp-tiendas.
- BD dev: menú «Home»→«Inicio»; página duplicada 13 «Checkout» (solo el shortcode, no era la de WooCommerce, ID 8) borrada; respaldo en `~/dlp-backups/pagina-13-checkout-duplicada.json`; título del logo en el header (respaldo en `~/dlp-backups/header-layout-158753-antes-titulo-logo.txt`).
- Pendiente de revisar a mano: correos (desactivados en dev), URLs en inglés (`/mi-cuenta/orders/`, `/edit-account/`, `/payment-methods/`), `title="empty"` de un iframe invisible de la pasarela.

## 0.34.x — Complementos en listas agrupadas (feedback 2026-10-26)
- El cliente no quiere los botones − / + para los complementos. Felices los 4 (4), Trío de Clásicas (3), Trío Del Puente (3) y Las Favoritas (4) pasan a N listas «Complemento 1…N» (Papas fritas incluida, Cheese poppers y Aros de cebolla +Q10) dentro de un acordeón `[acordeon:Elige tus N complementos]` con contador «0 de N» + «Obligatorio». Carnuda Lovers agrupa «Complemento 1/2» igual.
- JS: `accSync`/`accordions` de home-quickview cuentan las listas del acordeón y resumen solo lo elegido. Script de datos: `scripts/complementos-en-lista.php` (copia previa en `_product_addons_backup_cantidades`; la anterior de cantidades en `_product_addons_backup_complementos`).
- Los bebidas siguen como grupo de cantidad (`[grupo:…=N]`). Los combos con un solo complemento no cambian.

## 0.34.3 — Carrito lateral asomándose (iPhone/Android)
- El panel del carrito (Xootix) cerrado solo se escondía con `right:-350px`; en teléfonos reales se veía una franja al cargar y al cambiar de categoría (no reproducible en el emulador del navegador). Ahora cerrado queda `visibility:hidden` (carrito-lateral/style.css) y se muestra al abrir; la animación se conserva.

## 0.35.x — Editar productos del carrito lateral
- Al añadir, `woocommerce_add_cart_item_data` guarda en el ítem lo enviado en los campos `addon-*` (`dlp_edit`). `xoo_wsc_product_summary_col_end` pinta el botón «Editar» (`a.dlp-edit`, href `/menu/#editar-<cart_key>`) solo en ítems con esa selección: los que ya estaban en un carrito antes de 0.35 no lo tienen.
- JS (home-quickview): `#editar-<key>` cierra el carrito lateral, pide los datos por `?wc-ajax=dlp_fe_edit_data`, abre la vista rápida del producto, rellena el formulario (`prefill`), pone «Editando tu pedido» y el botón «Actualizar» y manda `dlp_edit_key`. En el servidor, `woocommerce_add_to_cart_validation` (prioridad 999, ya pasadas las demás validaciones) quita el ítem viejo y se añade el nuevo (queda al final).
- La vista rápida solo carga en home, tienda, categorías y /menu/; en las demás páginas el enlace lleva a /menu/ y allí abre la edición.
- Nota de pruebas: el navegador integrado deja de pintar si la pestaña no está al frente (`document.hidden`); comprobar el DOM.

## 0.36.x — Resumen corto de opciones en el carrito
- Módulo `carrito-resumen` (filtro `woocommerce_get_item_data`, prioridad 999; los Add-Ons usan `name` y `display` puede venir vacío): junta «Complemento 1…N», las cantidades de grupos `[grupo:…]` y «<hamburguesa>: quitar ingredientes» en 3 líneas («Complementos», «Bebidas», «Sin ingredientes»). Solo cambia lo que ve el cliente; el pedido y el panel guardan cada opción igual. El precio extra de cada opción ya no se lista (sigue en el subtotal).

## 0.37.x — Promos por producto
- Estándar: un acordeón por hamburguesa (`[acordeon:<Producto N>]`) con su complemento («<Producto N>: complemento», obligatorio) y «<Producto N>: quitar ingredientes»; después las bebidas incluidas (`[grupo:Elige tus N bebidas=N]`, sin precio) y «Añade bebida extra» (`[grupo:Añade bebida extra=*]`, filas «Extra · <bebida>» con precio: Frezka Q17, Agua Q9, resto Q11). La carne favorita queda arriba.
- Hecho: Carnuda Lovers (`scripts/promo-carnuda-estandar.php`) y Felices los 4 (`scripts/promo-estandar.php 122472`). Pendiente: 126123 Trío de Clásicas, 126125 Trío Del Puente, 143397 Las Favoritas (ya configuradas en el script). Copia previa en `_product_addons_backup_estandar`.
- Quickview: el nombre «<Título>: …» pierde el prefijo dentro del acordeón; el resumen del acordeón con lista + casillas es «Papas fritas · Sin Queso»; «Obligatorio» en el acordeón con una lista requerida hasta elegir.
- Carrito (`carrito-resumen`): una línea por producto («Del Puente 2: Papas fritas · Sin Queso Americano»), «Bebidas» y «Bebida extra» agrupadas. El hash del carrito incluye `DLP_FE_VERSION` para que el navegador vuelva a pedir el HTML del carrito tras cada versión (sessionStorage lo guardaba y mostraba el formato anterior).
- `scripts/deploy-dev.sh`: sube el plugin a dev por rsync (sin GitHub) para iterar rápido.

## 0.38.0 — Felices los 4: carne y bebida por combo
- Cada hamburguesa trae en su acordeón: carne (Premium Blend / Tortas Angus +Q12; Angus = +Q48 entre 4 o +Q36 entre 3), complemento, bebida (una, obligatoria, sin precio) y quitar ingredientes. Se quitan la carne general y el grupo «Elige tus N bebidas». «Añade bebida extra» se queda. `scripts/promo-estandar.php <id>` parte siempre de la copia `_product_addons_backup_estandar`; Hechos los cinco: Carnuda Lovers, Felices (122472), Trío de Clásicas (126123), Trío Del Puente (126125) y Las Favoritas (143397).
- Quickview: grupo abierto = una sola tarjeta con borde y 24 px de separación del siguiente; «Obligatorio» en el acordeón hasta que todas sus listas estén elegidas (el contador «N de M» queda solo para «Elige tus N …»).
- Carrito: «Del Puente 1: Premium Blend · Papas fritas · Frezka Tamarindo · Sin Queso» (carne/complemento/bebida por producto).

## 0.38.1 — /menu/ en blanco en desktop
- Divi 5 (modo compatibilidad con módulos Divi 4) regeneró su CSS y la columna del carrusel de categorías quedó como `float:left; width:auto`: el carrusel (Swiper, anchos en px según su contenedor) la estiraba a ~725 000 px y la página se veía en blanco en escritorio. `home-nuevo/style.css` fija esa columna a 100% con `:has(.navMenu_slide), :has(.main_slider)`, sin depender del CSS de Divi. Si pasa algo parecido en otra página con Swiper, revisar primero el ancho de la columna padre (`getBoundingClientRect`) y la regla `.et_block_row .et_pb_column { float:left }`.
- Bebida dentro de cada combo (promo-estandar.php): solo las 3 Frezkas van incluidas; Coca Cola, Coca Cola Zero, Orange Crush, Tiky y Canada Dry +Q11; Agua pura +Q9 (mismos precios que como extra).

## 2026-10-26 — «papas fritas» y «Frezka natural» en todos los productos
- `scripts/papas-frezkas-textos.php` (dev, idempotente, copia en `_dlp_textos_backup_papasfrezka` con título/corta/larga/extras): «papa(s)» → «papa frita / papas fritas»; «Frezka(s)» → «Frezka natural / Frezkas naturales» (no si ya dice natural, si sigue el sabor —Tamarindo, Jamaica…— ni «Frezka Té frío»; «palitos de papa» no se toca). Solo texto: no HTML, shortcodes ni URLs. 35 productos / 46 textos en dev (incluye títulos como «La Clásica + Frezka natural», «Papas fritas individuales» y las opciones de extras).
- Para producción: correr el script ahí (primero con `dry`), o replicar el resultado al migrar. Los nombres de bebida como extras («Frezka Tamarindo») NO cambian.

## 2026-10-26 — SMASH sin Angus
- `scripts/quitar-angus-smash.php`: quita «Elige tu carne favorita» (Premium Blend / Tortas Angus) de las SMASH que lo tenían: Legendaria Individual (159004) y Jalapeño Popper Individual (159002); los combos SMASH y la Clásica SMASH Individual ya no lo tenían. Copia previa en `_product_addons_backup_angus`.

## 0.38.3 — Página Restaurantes (opción A)
- Módulo `restaurantes` pinta `/restaurantes/` (ID 2253) sobre la página existente; su contenido de Divi (34 botones a páginas de tienda) se conserva sin mostrarse. Slug, textos y color en DLP Config → Inicio y catálogo → Restaurantes.
- Datos de dlp-tiendas: tiendas `extra_store` habilitadas (`dlp_tiendas_store_flags`), punto `dlp_tiendas_store_point` (si no, `extra_store_lat/lng`), dirección `extra_store_address`, teléfono `extra_store_phone`, abierto/cerrado con `dlp_tiendas_service_status` y el horario de hoy (el HTML se pinta en cada visita, no se cachea).
- Mapa: Leaflet 1.9.4 + Protomaps de dlp-tiendas (`DLP_TIENDAS_URL . assets/vendor`, config `dlp_tiendas_map_config()`); pines con agrupación propia a 36 px.
- Región de cada tienda: meta `_dlp_region` (guatemala, centro, occidente, costa, oriente); sin valor se deduce del nombre (`dlp_fe_rs_region_defecto`). Caja «Página Restaurantes» en la tienda para cambiarla u ocultarla (`_dlp_ocultar_web`).
- Acciones: «Abrir mapa» (Google Maps por coordenadas), «Cómo llegar» (Waze) y teléfono (`tel:+502…`). Sin coordenadas usan la dirección escrita. «Cerca de mí» usa la geolocalización del navegador o un punto elegido tocando el mapa; distancia en línea recta.
- 0.38.4: pines con ícono de tienda; burbuja con nombre + íconos (mapa, Waze, teléfono); la tienda seleccionada muestra los botones completos; tocar el mapa fija el punto (sin botón «Elegir un punto»); créditos con la bandera. Contenido viejo de Divi de la página 2253 borrado (respaldo `~/dlp-backups/pagina-2253-restaurantes-contenido-divi.json`).
- 0.38.5: estilo de mapa propio (objeto `BASE` en `modules/restaurantes/script.js`, tema claro de Protomaps con parques menta, agua celeste, calles blancas, edificios con borde y textos grises; se arma con `protomapsL.paintRules/labelRules`, sin cambiar los tiles). Pines de gota negra con ícono de tienda; el seleccionado en el color de acento. El mapa del checkout (dlp-tiendas) conserva su estilo original.
- 0.38.6: el estilo de mapa y los pines salieron a `dlp-tiendas/assets/js/map-style.js` (0.14.2): `DLP.baseLayer(url)` y `DLP.pinIcon({tipo:'tienda'|'punto', color})`. Lo usan Restaurantes, el checkout y Mi cuenta → Direcciones (el pin del cliente es la gota negra con punto blanco). El pin de tienda seleccionado solo cambia de color (círculo blanco con ícono negro). El mapa del admin de dlp-tiendas no cambia.
- 0.38.7: **mapa 3D solo en Restaurantes**: MapLibre GL 5.24 + pmtiles 4.5 (en `modules/restaurantes/mapa/vendor`), estilo `mapa/style.json` generado con `scripts/generar-estilo-mapa.mjs` (paleta de dlp-tiendas `DLP.PALETA` + `@protomaps/basemaps`, edificios `fill-extrusion` con `height`/`min_height`, 9 m por defecto), glifos Noto Sans propios en `mapa/fonts`. Se inclina (55°) al seleccionar una tienda y vuelve plano al alejarse; gestos cooperativos (Ctrl/⌘ + scroll, dos dedos). Sin WebGL queda solo la lista. Checkout y Mi cuenta siguen en Leaflet plano. Cobertura de edificios = la de OpenStreetMap (parcial en Guatemala). Para cambiar colores: editar la paleta en `dlp-tiendas/assets/js/map-style.js` y regenerar `style.json`.
- 0.38.9: las librerías del mapa se cargan **solo al ver el mapa** en /restaurantes/ (no en otras páginas): MapLibre ~357 KB comprimidos + 20 KB; sin WebGL, con ahorro de datos o conexión 2G/3G se usa Leaflet plano (`?mapa=plano` lo fuerza para probar). En celular el mapa mide 70 % del alto de pantalla y tiene botón de pantalla completa (clase `.full`, Esc la cierra).
- Lo que no hay en esta versión: «Pedir aquí» (descartado), horario completo por día, ficha por tienda.

## 0.39.0 — Mapa 3D en todos los mapas del front
- El mapa 3D (MapLibre GL + PMTiles, respaldo Leaflet sin WebGL / ahorro de datos / ?mapa=plano) vive en **dlp-tiendas 0.15.0**: `assets/js/map-3d.js` (`DLP.pinMapa`, `DLP.cargarMapa`, `DLP.estiloGL`, `DLP.motorMapa`), `assets/mapa3d/` (librerías, `style.json`, fuentes) y la config `dlp_tiendas_map_config()` (`libs`, `mapa3d`, `ver`). Las librerías se piden solo cuando se crea un mapa.
- Lo usan: checkout (`checkout-map.js`), Mi cuenta → Direcciones (`cuenta-pagina/script.js`), `/ubicacion-entrega/` (`ubicacion.js`) y Restaurantes (adaptadores propios para marcadores y burbuja). Se quitó de dlp-frontend `modules/restaurantes/mapa` y el script generador (ahora `dlp-tiendas/scripts/generar-estilo-mapa.mjs`).
- Pin pickers: gesto cooperativo (Ctrl/⌘ + scroll, dos dedos) salvo `/ubicacion-entrega/`; inclinan 40° al acercar. No cambian: mapa del admin de tiendas (Leaflet) ni el mapa del panel de pedidos (dlp-panel3).

## 0.40.0 — Ícono del Menú (vapor / mordida)
- Módulo nuevo `modules/icono-menu` (sección Apariencia, pestaña «Ícono del Menú»). Reemplaza el ícono del botón «Menú» con el logo del cliente (`logo-favicon-DP.svg`, de línea fina): **sin seleccionar** sale vapor; **en la página Menú** (`body.dlp-en-menu`) el logo aparece mordido (bocado grande con borde dentado, tres muescas) y caen migajas. Propuesta elegida por el usuario: «Un bocado grande» (opción 2 de la 2.ª ronda), con el vapor de la «Humeante».
- `dlp_fe_im_svg()` genera el SVG (máscara que recorta el contorno + círculos con trazo que dibujan el filo nuevo, recortados a la silueta del logo). Lo usa `menu-inferior/module.php` (celular) y un `<template id="dlp-im-tpl">` en `wp_footer` que `script.js` clona dentro del botón del header de Divi cuyo enlace termina en `/menu/` (se busca por enlace, no por clase `et_pb_button_N_tb_header`), a la izquierda del texto.
- Divi pone el relleno de sus botones con `body #page-container .et_pb_section .et_pb_button_N_tb_header … !important`: para ganarle, el relleno izquierdo del botón (2.8em) se fija con `html body #page-container .et_pb_section a.et_pb_button.dlp-im-btn` (el `.et_pb_section` va DENTRO de `.et-l--header`, no fuera). El ícono de Divi (`::before/::after`) se oculta solo en ese botón.
- Menú inferior: `.dlpmi-it svg` es de línea (`fill:none`); el ícono nuevo va relleno (`svg.dlp-im{fill:currentColor;stroke:none}`).
- Verificado en dev: header de escritorio en /restaurantes/ (vapor) y /menu/ (mordida), barra inferior en celular (375 px) en ambas. Animaciones CSS, respetan «reducir movimiento».
- **0.40.1**: el ícono del menú inferior mide 23 px (el mismo peso visual que los otros tres). El estado «en el Menú» (mordida, sin vapor) ahora también se activa en la ficha de producto (`is_product()`) y mientras hay una ventana de producto abierta (`html.dlpqv-lock`). En el header, el botón MENÚ queda «activo» (fondo `rgb(247,247,247)` y texto negro, igual que su hover) en esos mismos estados; en celular, el botón Menú de la barra se marca activo con la ventana de producto abierta.
- **0.40.2**: el ícono del botón MENÚ del header queda centrado (vertical y horizontal): margen superior -.95em; medido en dev: centro del logo 66,3 px vs botón 66 px, hueco izquierdo 13,3 px vs derecho 13,2 px.

## 0.41.0 — Ícono de Restaurantes (pin con el logo)
- Elegido por el usuario: «Pin con logo» (sin mapa de Guatemala; se probaron mapas y se descartaron), con la hamburguesa más pequeña dentro del pin para dejar aire (`dlp_fe_ip_svg()`, logo a escala .056, trazo reforzado 12).
- Se usa en: botón RESTAURANTES del header de Divi (a la izquierda del texto, igual que el Menú; se detecta por enlace a `/restaurantes/`), botón «Ubicaciones» del menú inferior del celular, y los pines del mapa de /restaurantes/ (ver dlp-tiendas 0.15.3).
- Estado «en la página»: `body.dlp-en-restaurantes` → el pin salta como pelota con sombra (CSS) y el botón del header queda activo (fondo `rgb(247,247,247)`, texto negro). Fuera de /restaurantes/ está quieto.
- Header: las clases `dlp-ic-btn` (común: relleno izquierdo 2.8em, oculta el ícono de Divi) + `dlp-im-btn` / `dlp-ip-btn` las pone `script.js` desde los `<template id="dlp-im-tpl|dlp-ip-tpl">`.
- Mapas en perspectiva de Guatemala: se trazó el contorno a partir de una imagen enviada por el usuario; no se usó (la forma sola no convenció a 24 px).
