#!/bin/sh
# Installs the website's PHP packages (run by the composer service on each start).
# If web/composer.local.json exists (a district's own modules, or local module folders while developing),
# Composer runs from it instead: it pulls in composer.json and keeps its own composer.local.lock,
# so Core's tracked files never change.
set -e

if [ -f composer.local.json ]; then
  # Core's own packages first: they include the plugin that reads composer.local.json.
  [ -d vendor/wikimedia/composer-merge-plugin ] || composer install --no-interaction --no-progress
  export COMPOSER=composer.local.json
  if [ ! -f composer.local.lock ] || [ composer.local.json -nt composer.local.lock ] || [ composer.json -nt composer.local.lock ]; then
    exec composer update --no-interaction --no-progress
  fi
fi

exec composer install --no-interaction --no-progress
