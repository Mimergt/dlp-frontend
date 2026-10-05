# DLP Frontend

Plugin de front-end de Del Puente (CSS/JS por módulos). Se actualiza solo desde GitHub (rama `main`) con Plugin Update Checker, igual que `epic-lts_api`.

## Publicar un cambio
1. Subir `Version:` y `DLP_FE_VERSION` en `dlp-frontend.php`.
2. Push a `main`. WP lo detecta en el admin (Plugins → Buscar actualizaciones).

## Módulos
Cada carpeta en `modules/<id>/` con un `module.json`:
```json
{ "label": "Nombre", "description": "…", "where": ["checkout","cart"], "default": true,
  "css": "style.css", "js": "script.js", "php": "module.php" }
```
- Se activan/desactivan y se les cambia el "dónde" en **Ajustes → DLP Frontend**.
- Un módulo roto (PHP/JSON/CSS/JS) solo afecta a ese módulo.
- `"boot": true` + `"php": "module.php"`: módulo de **lógica PHP siempre cargada** (donde antes corría el `functions.php` del tema): login de la app, campos del checkout, etc. Se activan/desactivan en el admin; emergencia: `define('DLP_FE_DISABLED_MODULES', 'id1,id2');` en `wp-config.php`.
- Mientras el tema legacy `dlp` esté activo, los módulos boot esperan (el tema ya tiene esa lógica). Al activar `DLP26` arrancan solos.
- Admins: la barra superior muestra los módulos cargados y permite apagarlos solo en tu vista (`?dlp_fe_off=id` o `?dlp_fe_off=all`).
- Emergencia: `define('DLP_FE_DISABLED', true);` en `wp-config.php` apaga todo el plugin.
