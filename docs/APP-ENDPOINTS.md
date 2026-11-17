# Endpoints que usa la app móvil (webview) y rutas REST públicas

Lo que se puede inferir del código del sitio. **No hay acceso al código de la app nativa**: la pantalla «Obteniendo información…» es de la app
(no existe en dlp-frontend, dlp-tiendas, dlp-panel3 ni el tema), así que no se sabe con certeza qué consulta al arrancar.

## Del lado del sitio (módulo `app-login`, copia fiel del tema dlp)
| Qué | Dónde | Notas |
|---|---|---|
| Registro / ingreso desde la app | `admin-ajax.php?action=custom_register_from_app` (POST, con y sin sesión) | CORS por `dlp_fe_cors_ajax_actions`; `remember=true` desde 0.60.1 |
| Registro (REST) | `/wp-json/auth/v1/register_from_app/` | pública |
| Autologin | `/?autologin=1&hash=base64(usuario:::clave:::correo)&device=Android|IOS` | `wp_signon` con `remember=true` (30 días para clientes); luego la app trabaja en `/?origin=webview&device=…` |
| Marca de webview | parámetro `origin=webview` | un script en `wp_head` guarda la cookie `dlp_app` (ver `includes/` y `modules/login-pagina`) |

## REST públicas relevantes (`dlp/v1`, plugin dlp-tiendas)
- `GET /wp-json/dlp/v1/sitio/estado`: estado/horario del sitio (la barra de aviso la consulta cada 60 s). **Pública.**
- `POST /wp-json/dlp/v1/ubicacion/punto`: cobertura de un punto. Pública.
- `POST /ubicacion/enlace`, `GET /ubicacion/estado`, `POST /cobertura`, `GET /pedido/pickup`, `POST /pedido/armar`, `POST /pedido/crear`: API del chat/«cerebro», con cabecera `x-dlp-secret`.

## Regla: una ruta pública no debe caer por un nonce vencido
WordPress responde 403 `rest_cookie_invalid_nonce` a cualquier ruta si llega un `X-WP-Nonce` que no valida (incluso sin cookie). Medido el 17-11-2026
en producción: 6 respuestas 403 en `sitio/estado`. `includes/rest-publico.php` (0.60.4) ignora ese error en la lista de rutas públicas
(`dlp_fe_rest_rutas_publicas`) y las trata como anónimas; `dlp-paneles` y el resto siguen dando 403. dlp-tiendas 0.16.9 además reintenta sin
nonce si recibe 403.

## Quién incrusta un nonce `wp_rest` en el HTML
- dlp-tiendas `includes/horario-general.php`: solo si `is_user_logged_in()` (para el bypass del administrador). Un cliente anónimo no lleva nonce.
- dlp-panel3 `includes/app_mode.php` y `shortcode.php`: `/orders/` y el panel; respuesta con `nocache_headers()`.
- Si una página con sesión se sirviera desde caché, mostraría el nonce de otro usuario. Revisar en Varnish/Cloudflare: **no cachear** respuestas con la cookie
  `wordpress_logged_in_*`, ni `/orders/`, `/mi-cuenta/`, `/finalizar-compra/`, `/ingresar/`, `/wp-json/dlp-paneles/*`.

## Pendiente (necesita el código de la app)
Confirmar qué consulta la app antes de mostrar el home (¿`sitio/estado`, `restaurantes`, otro?) y qué timeout/errores maneja; la app debería
mostrar un reintento si la primera consulta falla en vez de quedarse en «Obteniendo información…».
