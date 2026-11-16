#!/bin/bash
# Installs the host helper (rebuilder.sh) as a systemd service for this copy of Tornado Engine,
# so the Rebuild button on the website works. Run once on the server: sudo ./host/install.sh
# Remove it with: sudo ./host/install.sh --remove
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Run it with sudo." >&2; exit 1; }

root=$(cd "$(dirname "$0")/.." && pwd)
owner=$(stat -c %U "$root")
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

[Service]
User=$owner
WorkingDirectory=$root
ExecStart=/bin/bash $root/host/rebuilder.sh
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
UNIT

systemctl daemon-reload
systemctl enable --now "$unit"
echo "Installed $unit. The Rebuild button on the website works now."
