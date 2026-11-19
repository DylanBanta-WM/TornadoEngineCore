#!/bin/bash
# Tornado Engine's host helper: rebuilds the containers when an admin clicks Rebuild on the
# website. The website only leaves a request in web/storage/app/tornado; this runs on the server
# (as a systemd service, see install.sh), so no container needs Docker access.
#
# It follows the containers: while they're down (docker compose down) it waits and touches nothing,
# and it carries on by itself after docker compose up -d. If this copy's folder is deleted, it stops.
# The service runs it from memory (see install.sh), so it never holds a file open in the folder.
set -u
cd "${TORNADO_ROOT:-$(dirname "$0")/..}" || exit 0
root=$(pwd)
dir=web/storage/app/tornado

# Compose's project name: COMPOSE_PROJECT_NAME in .env, otherwise the folder's name.
project() {
  local name
  name=$(sed -n 's/^COMPOSE_PROJECT_NAME=//p' .env 2>/dev/null | tail -n 1 | tr -d '"')
  [ -n "$name" ] || name=$(basename "$root" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')
  echo "$name"
}

running() { # this copy's website container is up
  [ -n "$(docker ps -q --filter "label=com.docker.compose.project=$(project)" --filter label=com.docker.compose.service=app 2>/dev/null)" ]
}

status() { # status STATE [EXIT]
  printf '{"state":"%s","started":"%s","finished":"%s","exit":%s}\n' "$1" "$started" "${3:-}" "${2:-null}" > "$dir/rebuild.status.tmp"
  mv "$dir/rebuild.status.tmp" "$dir/rebuild.status"
}

while true; do
  # The folder's gone (deleted, or moved for a fresh clone): stop for good.
  [ -f "$root/docker-compose.yml" ] || exit 0
  cd "$root" 2>/dev/null || exit 0

  if running; then
    mkdir -p "$dir"
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
  fi
  # Waits outside the folder, so nothing in it is busy.
  cd / && sleep 3
done
