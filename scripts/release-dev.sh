#!/usr/bin/env bash
# Publica la versión actual de main en dev.delpuente.com.gt: espera a que GitHub la sirva, la instala con el
# actualizador de WordPress (igual que "Actualizar" en el admin) y purga la caché de Cloudflare de los assets.
# Uso: ./scripts/release-dev.sh   (después de hacer commit y push a main)
set -euo pipefail
cd "$(dirname "$0")/.."
WANT=$(grep -m1 "^ \* Version:" dlp-frontend.php | awk '{print $3}')
echo "Versión local: $WANT"
for i in $(seq 1 30); do
  GOT=$(curl -s "https://raw.githubusercontent.com/Mimergt/dlp-frontend/main/dlp-frontend.php?cb=$(date +%s)" | grep -m1 "Version:" | awk '{print $3}')
  [ "$GOT" = "$WANT" ] && break
  echo "GitHub sirve $GOT, esperando $WANT… ($i)"; sleep 10
done
[ "$GOT" = "$WANT" ] || { echo "GitHub no sirvió $WANT. ¿Hiciste push a main?"; exit 1; }
SSH="ssh -i $HOME/.ssh/id_ed25519_dlp_tiendas -p 65002 u437592302@109.106.250.100"
$SSH 'cd ~/domains/dev.delpuente.com.gt/public_html && wp eval "require_once WP_PLUGIN_DIR.\"/dlp-frontend/plugin-update-checker/plugin-update-checker.php\"; \$c=YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(\"https://github.com/Mimergt/dlp-frontend/\",WP_PLUGIN_DIR.\"/dlp-frontend/dlp-frontend.php\",\"dlp-frontend\"); \$c->setBranch(\"main\"); \$c->checkForUpdates(); delete_site_transient(\"update_plugins\"); wp_update_plugins();" 2>&1 | grep -v autologin; wp plugin update dlp-frontend 2>&1 | grep -v autologin | tail -2; wp plugin list --name=dlp-frontend --fields=name,status,version 2>&1 | grep -v autologin'
./scripts/purge-dev.sh
