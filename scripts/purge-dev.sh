#!/usr/bin/env bash
# Purga de Cloudflare SOLO de los CSS/JS de dlp-frontend en dev.delpuente.com.gt (Cloudflare ignora ?ver=).
# Usa el mismo token que dlp-tiendas: ~/.config/dlp-tiendas/cloudflare.env (CF_API_TOKEN, opcional CF_ZONE_ID).
# Correr después de cada release o cambio de CSS/JS en dev.
set -euo pipefail
cd "$(dirname "$0")/.."
[ -f "$HOME/.config/dlp-tiendas/cloudflare.env" ] || { echo "Falta ~/.config/dlp-tiendas/cloudflare.env"; exit 1; }
set -a; . "$HOME/.config/dlp-tiendas/cloudflare.env"; set +a
BASE="https://dev.delpuente.com.gt/wp-content/plugins/dlp-frontend"
FILES=$(find modules -type f \( -name '*.js' -o -name '*.css' \) | sed "s#^#\"$BASE/#; s#\$#\"#" | paste -sd, -)
RESP=$(curl -s -X POST "https://api.cloudflare.com/client/v4/zones/${CF_ZONE_ID:-c2f844fce45d098adb50efd12c5b39c9}/purge_cache" \
  -H "Authorization: Bearer $CF_API_TOKEN" -H "Content-Type: application/json" --data "{\"files\":[$FILES]}")
echo "$RESP" | grep -q '"success":true' && echo "Purga Cloudflare (solo assets de dlp-frontend en dev): OK" || { echo "Falló: $RESP"; exit 1; }
