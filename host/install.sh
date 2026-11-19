#!/bin/bash
# Pre-run setup for this copy of Tornado Engine. Run once on the server, before the first
# docker compose up -d: sudo ./host/install.sh
#  - Makes .env from .env.example if there isn't one, and fills in APP_KEY and DB_PASSWORD when they're
#    empty. Values already set are left alone (the database keeps the password it was made with); an
#    APP_KEY pasted without its "base64:" prefix gets it. No secrets are printed.
#  - Installs the host helper (rebuilder.sh) as a systemd service, so the Rebuild button on the website works.
# Remove only the helper with: sudo ./host/install.sh --remove (host/uninstall.sh removes everything).
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Run it with sudo." >&2; exit 1; }

root=$(cd "$(dirname "$0")/.." && pwd)
owner=$(stat -c %U "$root")

if [ "${1:-}" != "--remove" ]; then
  env="$root/.env"
  if [ ! -f "$env" ]; then
    cp "$root/.env.example" "$env"
    echo "Made .env from .env.example."
  fi
  chown "$owner:$(stat -c %G "$root")" "$env"
  chmod 600 "$env"

  value() { sed -n "s/^$1=//p" "$env" | tail -n 1; }
  set_value() {
    # Base64 and hex values only, so nothing awk needs escaped.
    awk -v name="$1" -v val="$2" '$0 ~ "^" name "=" { print name "=" val; done = 1; next } { print }
      END { if (! done) print name "=" val }' "$env" > "$env.new"
    cat "$env.new" > "$env"
    rm -f "$env.new"
  }

  key=$(value APP_KEY)
  if [ -z "$key" ]; then
    set_value APP_KEY "base64:$(openssl rand -base64 32)"
    echo "Set APP_KEY."
  elif [ "${key#base64:}" = "$key" ] && [ "${#key}" -eq 44 ]; then
    set_value APP_KEY "base64:$key"
    echo "Added base64: to APP_KEY."
  fi
  if [ -z "$(value DB_PASSWORD)" ]; then
    set_value DB_PASSWORD "$(openssl rand -hex 32)"
    echo "Set DB_PASSWORD."
  fi
fi

project=$(grep -E '^COMPOSE_PROJECT_NAME=' "$root/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' || true)
unit="tornado-rebuilder-${project:-tornado}.service"

if [ "${1:-}" = "--remove" ]; then
  systemctl disable --now "$unit" 2>/dev/null || true
  rm -f "/etc/systemd/system/$unit"
  systemctl daemon-reload
  echo "Removed $unit."
  exit 0
fi

id -nG "$owner" | grep -qw docker || { echo "$owner (who owns $root) needs to be in the docker group." >&2; exit 1; }

cat > "/etc/systemd/system/$unit" <<UNIT
[Unit]
Description=Tornado Engine rebuilder ($root)
After=docker.service
Requires=docker.service
# Not started without its folder (deleted without host/uninstall.sh), so it doesn't keep retrying.
ConditionPathExists=$root/docker-compose.yml

[Service]
User=$owner
WorkingDirectory=$root
Environment="TORNADO_ROOT=$root"
# Read into memory, so no file in the folder stays open (on network storage a deleted, open file can't be removed).
ExecStart=/bin/bash -c 'exec /bin/bash -c "\$\$(cat host/rebuilder.sh)" rebuilder'
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now "$unit"
echo "Installed $unit. The Rebuild button on the website works once the site is running."
