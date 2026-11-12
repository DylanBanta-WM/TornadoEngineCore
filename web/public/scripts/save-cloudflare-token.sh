#!/usr/bin/env bash
# Saves your Cloudflare API token where Certbot looks for it. Run it on the Nginx RP:
#   sudo bash save-cloudflare-token.sh
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "Run this with sudo: sudo bash $0" >&2
  exit 1
fi

dir=/root/.secrets/certbot
file="$dir/cloudflare.ini"

# The token isn't shown as you paste it, and doesn't end up in your shell history.
read -r -s -p "Cloudflare API token: " token
echo
token="${token//[[:space:]]/}"

if [ -z "$token" ]; then
  echo "No token entered. Nothing was saved." >&2
  exit 1
fi

umask 077
mkdir -p "$dir"
chmod 700 /root/.secrets "$dir"
printf '# Cloudflare API token used by Certbot\ndns_cloudflare_api_token = %s\n' "$token" > "$file.new"
mv "$file.new" "$file"
chmod 600 "$file"

echo "Saved to $file (readable only by root)."
