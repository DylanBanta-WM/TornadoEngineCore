#!/bin/bash
# Tornado Engine's host helper: rebuilds the containers when an admin clicks Rebuild on the
# website. The website only leaves a request in web/storage/app/tornado; this runs on the server
# (as a systemd service, see install.sh), so no container needs Docker access.
set -u
cd "$(dirname "$0")/.." || exit 1
dir=web/storage/app/tornado
mkdir -p "$dir"

status() { # status STATE [EXIT]
  printf '{"state":"%s","started":"%s","finished":"%s","exit":%s}\n' "$1" "$started" "${3:-}" "${2:-null}" > "$dir/rebuild.status.tmp"
  mv "$dir/rebuild.status.tmp" "$dir/rebuild.status"
}

while true; do
  date -Is > "$dir/rebuild.heartbeat"
  if [ -f "$dir/rebuild.request" ]; then
    rm -f "$dir/rebuild.request"
    started=$(date -Is)
    status running
    docker compose up -d --build --remove-orphans > "$dir/rebuild.log" 2>&1
    code=$?
    # nginx's config files are mounted one by one, so a container keeps the old file until it's recreated.
    [ $code -eq 0 ] && { docker compose up -d --force-recreate --no-deps nginx-web nginx-harness >> "$dir/rebuild.log" 2>&1; code=$?; }
    status "$([ $code -eq 0 ] && echo done || echo failed)" "$code" "$(date -Is)"
  fi
  sleep 3
done
