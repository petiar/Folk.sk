#!/usr/bin/env bash
# Skopíruje frontend knižnice z Composer vendoru do web/libraries/
# Spusti po: composer install
set -e

WEBROOT="$(dirname "$0")/../drupal/web"
VENDOR="$(dirname "$0")/../drupal/vendor"

echo "Kopírujem Bootstrap..."
mkdir -p "$WEBROOT/libraries/bootstrap/dist"
cp -r "$VENDOR/twbs/bootstrap/dist/css" "$WEBROOT/libraries/bootstrap/dist/"
cp -r "$VENDOR/twbs/bootstrap/dist/js"  "$WEBROOT/libraries/bootstrap/dist/"
echo "  ✓ Bootstrap → web/libraries/bootstrap/"

echo "Hotovo."
