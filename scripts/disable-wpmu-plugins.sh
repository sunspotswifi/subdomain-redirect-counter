#!/bin/bash
#
# Disable WordPress MU plugins on development server
#
# Usage: ./scripts/disable-wpmu-plugins.sh [--enable]
#

DEV_HOST="agent@lexx"
WP_ROOT="/var/www/html/cardandcraft.lan/public_html"
MU_PLUGINS_DIR="$WP_ROOT/wp-content/mu-plugins"

if [ "$1" = "--enable" ]; then
    echo "Re-enabling MU plugins on development..."
    ssh "$DEV_HOST" "
        cd '$MU_PLUGINS_DIR' 2>/dev/null || exit 0
        for f in *.php.disabled; do
            [ -f \"\$f\" ] && mv \"\$f\" \"\${f%.disabled}\"
        done
        echo 'MU plugins re-enabled:'
        ls -la '$MU_PLUGINS_DIR' 2>/dev/null || echo '(no mu-plugins directory)'
    "
else
    echo "Disabling MU plugins on development..."
    ssh "$DEV_HOST" "
        cd '$MU_PLUGINS_DIR' 2>/dev/null || { echo 'No mu-plugins directory'; exit 0; }
        for f in *.php; do
            [ -f \"\$f\" ] && mv \"\$f\" \"\$f.disabled\"
        done
        echo 'MU plugins disabled:'
        ls -la '$MU_PLUGINS_DIR' 2>/dev/null || echo '(empty)'
    "
fi

echo "Done."
