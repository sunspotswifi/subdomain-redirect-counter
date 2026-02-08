#!/bin/bash
#
# Disable heavy plugins on development server using WP-CLI
#
# Usage: ./scripts/disable-dev-plugins.sh [--enable]
#

DEV_HOST="agent@lexx"
WP_ROOT="/var/www/html/cardandcraft.lan/public_html"

# Plugin slugs to manage
PLUGINS="wp-defender wp-hummingbird iubenda-cookie-law-solution wp-smush-pro beehive-analytics ultimate-branding broken-link-checker disable-comments official-facebook-pixel the-hub-client smartcrawl-seo wpmu-dev-seo"

if [ "$1" = "--enable" ]; then
    echo "Re-enabling plugins on development..."
    ssh "$DEV_HOST" "
        cd '$WP_ROOT'
        for plugin in $PLUGINS; do
            if wp plugin is-installed \"\$plugin\" --allow-root 2>/dev/null; then
                wp plugin activate \"\$plugin\" --network --allow-root 2>/dev/null && echo \"Activated: \$plugin\"
            fi
        done
        echo ''
        echo 'Active plugins:'
        wp plugin list --status=active-network --allow-root --format=table
    "
else
    echo "Disabling plugins on development..."
    ssh "$DEV_HOST" "
        cd '$WP_ROOT'
        for plugin in $PLUGINS; do
            if wp plugin is-installed \"\$plugin\" --allow-root 2>/dev/null; then
                wp plugin deactivate \"\$plugin\" --network --allow-root 2>/dev/null && echo \"Deactivated: \$plugin\"
            fi
        done
        echo ''
        echo 'Active plugins:'
        wp plugin list --status=active-network --allow-root --format=table
    "
fi

echo ""
echo "Done."
