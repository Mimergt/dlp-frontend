#!/usr/bin/env bash
# Sube el plugin directo a dev (rsync) y purga la caché de Cloudflare, sin pasar por GitHub. Para iterar rápido:
# después de aprobar, subir con git push como siempre (release-dev.sh solo sirve cuando GitHub ya tiene la versión).
set -euo pipefail
cd "$(dirname "$0")/.."
SSH="ssh -i $HOME/.ssh/id_ed25519_dlp_tiendas -p 65002"
HOST="u437592302@109.106.250.100"
DEV="~/domains/dev.delpuente.com.gt/public_html"
rsync -az --exclude .git --exclude .DS_Store --exclude docs --exclude preview -e "$SSH" ./ "$HOST:$DEV/wp-content/plugins/dlp-frontend/"
$SSH $HOST "cd $DEV && wp plugin list --name=dlp-frontend --fields=name,version,status 2>/dev/null"
./scripts/purge-dev.sh
