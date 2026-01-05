#!/bin/sh
set -e

DOCROOT=/var/www/html
NC_USER=www-data
CONFIG="$DOCROOT/config/config.php"

# Ensure writable paths exist and have correct perms
mkdir -p "$DOCROOT/config" "$DOCROOT/data" "$DOCROOT/custom_apps" "$DOCROOT/themes"
chown -R $NC_USER:$NC_USER "$DOCROOT/config" "$DOCROOT/data" "$DOCROOT/custom_apps" "$DOCROOT/themes"
chmod 770 "$DOCROOT/config" "$DOCROOT/data"

exec "$@"
