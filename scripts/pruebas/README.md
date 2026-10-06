# Pruebas del checkout (solo dev)

`chk.py` simula a un cliente por HTTP: crea sesión, agrega al carrito (`?wc-ajax=add_to_cart`), lee el formulario del checkout y lo envía (`?wc-ajax=checkout`) con los campos que se le indiquen.

Escenarios cubiertos el 2026-10-06 (ver `docs/MEMORIA.md`): invitado con correo nuevo / existente, pickup (tienda, hora, tienda sin servicio), delivery sin cobertura / sin punto / datos faltantes / correo inválido / carrito vacío, mínimo de delivery (Q60), cliente con sesión (libreta de 3, dirección guardada, prellenado), producto con extras y comentario.

Uso rápido (Python 3, sin dependencias):
```python
import chk
s = chk.S()            # invitado; con sesión: chk.S((nombre_cookie_logged_in, valor))
s.add(122456, 3)       # producto simple Q25 x3
print(s.place({**chk.BASEF, "woofood_order_type":"delivery", "billing_dlp_lat":"14.6349", "billing_dlp_lng":"-90.5069",
               "dlp_geo_source":"pin", "billing_address_2":"Prueba", "billing_address_name":"Casa", "billing_email":"prueba-x@example.com"}))
```
- Cookie de sesión para un usuario de prueba: `wp eval 'echo wp_generate_auth_cookie($id, time()+7200, "logged_in");'` (nombre: `LOGGED_IN_COOKIE`).
- Puntos con cobertura en dev: Calle Martí (14.6349, -90.5069), San Cristóbal (14.5906, -90.5957). Sin cobertura: (14.8, -91.5).
- Después de probar: poner los pedidos de prueba en `cancelled` (nota «Prueba automatizada»).
