#!/usr/bin/env bash
# Gets the HTTPS certificate and adds the Tornado Engine site to the Nginx RP. Run it on the Nginx RP:
#   sudo bash add-tornado-engine-site.sh
# You can also give the answers up front:
#   sudo bash add-tornado-engine-site.sh te.example.org 10.0.0.30:8090
set -euo pipefail

credentials=/root/.secrets/certbot/cloudflare.ini

fail() { echo "$1" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || fail "Run this with sudo: sudo bash $0"
command -v nginx >/dev/null || fail "Nginx isn't installed. Do step 2 first."
command -v certbot >/dev/null || fail "Certbot isn't installed. Do step 2 first."
[ -d /etc/nginx/sites-available ] && [ -d /etc/nginx/sites-enabled ] \
  || fail "This script expects /etc/nginx/sites-available and sites-enabled (Ubuntu, Debian, Raspberry Pi OS)."
[ -s "$credentials" ] || fail "No Cloudflare token at $credentials. Do step 4 first."

domain="${1:-}"
[ -n "$domain" ] || read -r -p "Tornado Engine's address (for example te.example.org): " domain
domain="$(echo "$domain" | tr '[:upper:]' '[:lower:]')"
domain="${domain#https://}"; domain="${domain#http://}"; domain="${domain%%/*}"
[[ "$domain" =~ ^[a-z0-9-]+(\.[a-z0-9-]+)+$ ]] || fail "\"$domain\" isn't a domain name."

upstream="${2:-}"
[ -n "$upstream" ] || read -r -p "Tornado Engine server's local IP and port (for example 10.0.0.30:8090): " upstream
upstream="${upstream#http://}"; upstream="${upstream%%/*}"
[[ "$upstream" =~ ^[A-Za-z0-9.-]+:[0-9]+$ ]] || fail "\"$upstream\" should look like 10.0.0.30:8090."

echo
echo "Getting the certificate for $domain..."
certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials "$credentials" \
  --deploy-hook "systemctl reload nginx" \
  -d "$domain"

site="/etc/nginx/sites-available/$domain"
backup=""
if [ -e "$site" ]; then
  backup="$site.bak-$(date +%Y%m%d%H%M%S)"
  cp "$site" "$backup"
  echo "Saved the old site config as $backup"
fi

cat > "$site" <<EOF
# Tornado Engine, added by add-tornado-engine-site.sh

# http:// visits go to https://
server {
    listen 80;
    server_name $domain;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name $domain;

    ssl_certificate     /etc/letsencrypt/live/$domain/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$domain/privkey.pem;

    # Reverse proxy: pass each visit on to Tornado Engine.
    location / {
        proxy_pass http://$upstream;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-Proto https;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Real-IP \$remote_addr;
    }
}
EOF

ln -sf "$site" "/etc/nginx/sites-enabled/$domain"

if ! nginx -t; then
  if [ -n "$backup" ]; then
    mv "$backup" "$site"
    echo "Nginx didn't accept the new config, so the old one was put back." >&2
  else
    rm -f "/etc/nginx/sites-enabled/$domain" "$site"
    echo "Nginx didn't accept the new config, so it was removed." >&2
  fi
  exit 1
fi

systemctl reload nginx

echo
echo "Done. Once your local DNS record is in place (the next step), open https://$domain/setup"
