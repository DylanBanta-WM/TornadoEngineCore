#!/bin/sh
# Installs the PHP extensions modules ask for ("php-extensions" in their composer.json, collected
# into php-extensions.txt by modules.php). Only PHP's standard extensions are accepted.
#   php-extensions.sh docker   the website's image (php:fpm): docker-php-ext-install
#   php-extensions.sh debian   the harness (Debian's php8.4 packages)
set -e
mode=$1
list=$(grep -E '^[a-z0-9_]+$' "$(dirname "$0")/php-extensions.txt" 2>/dev/null | sort -u || true)
[ -n "$list" ] || exit 0

libs=""; exts=""; packages=""
for ext in $list; do
  case "$ext" in
    bcmath|calendar|exif|pcntl|sockets|mysqli) lib="" ; pkg="php8.4-$ext" ;;
    gd)        lib="libpng-dev libjpeg-dev libfreetype-dev" ; pkg="php8.4-gd" ;;
    gmp)       lib="libgmp-dev"   ; pkg="php8.4-gmp" ;;
    intl)      lib="libicu-dev"   ; pkg="php8.4-intl" ;;
    ldap)      lib="libldap2-dev" ; pkg="php8.4-ldap" ;;
    pdo_pgsql|pgsql) lib="libpq-dev" ; pkg="php8.4-pgsql" ;;
    soap)      lib="libxml2-dev"  ; pkg="php8.4-soap" ;;
    zip)       lib="libzip-dev"   ; pkg="php8.4-zip" ;;
    *) echo "php-extensions: $ext isn't one of the extensions modules can ask for" >&2; exit 1 ;;
  esac
  libs="$libs $lib"; exts="$exts $ext"; packages="$packages $pkg"
done

apt-get update
if [ "$mode" = docker ]; then
  # shellcheck disable=SC2086
  apt-get install -y --no-install-recommends $libs
  case " $exts " in *" gd "*) docker-php-ext-configure gd --with-freetype --with-jpeg ;; esac
  # shellcheck disable=SC2086
  docker-php-ext-install -j"$(nproc)" $exts
else
  # Some (exif, pcntl, sockets, calendar) are built into Debian's php8.4-common/cli.
  for pkg in $packages; do
    apt-cache show "$pkg" >/dev/null 2>&1 && apt-get install -y --no-install-recommends "$pkg" || true
  done
fi
rm -rf /var/lib/apt/lists/*
