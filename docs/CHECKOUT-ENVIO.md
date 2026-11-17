# Envío en el checkout: CERO métodos de envío de WooCommerce

**Regla:** en `WooCommerce → Ajustes → Envío` no debe haber ningún método de envío activo en ninguna zona (ni «Envío gratuito»). Aplica a dev y a producción.

**Por qué:** el delivery de Del Puente se resuelve por cobertura en el mapa (plugin dlp-tiendas), sin tarifas. Con un método activo,
WooCommerce considera que el carrito necesita envío y exige calcular uno; con `ship_to_destination = billing_only` no llega a armar
ningún paquete y rechaza el pedido con «Por favor introduce una dirección para continuar» (código `shipping`). El 17-11-2026 pasó en
producción (se creó «Envío gratuito» en la zona GT) y rechazó ~87 % de los pedidos delivery desde móvil.

**Defensas en dlp-frontend (módulo `checkout-errores`):**
- `woocommerce_cart_needs_shipping` y `woocommerce_cart_needs_shipping_address` devuelven `false` (se apaga con el filtro
  `dlp_fe_ck_sin_envio_wc` → `false`): un método agregado por error ya no bloquea las compras.
- Aviso en el administrador (escritorio, WooCommerce, DLP, plugins) si `wc_get_shipping_method_count(true) > 0`.
- Migración única `dlp_fe_zona_estados_v1`: borra ubicaciones de zona con un departamento inexistente (p. ej. `state:GT:GT2`).
- Registro `dlp-checkout` (WooCommerce → Estado → Registros): las fallas `shipping` llevan la categoría `envio_wc` y el contexto
  técnico (necesita_envio, países, paquetes, tarifas, métodos_activos), sin datos personales.

**Comprobar:** `wp eval 'echo wc_get_shipping_method_count(true);'` debe dar `0`.
