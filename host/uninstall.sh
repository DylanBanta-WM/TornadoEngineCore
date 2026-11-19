#!/bin/bash
# Uninstalls this copy of Tornado Engine: its containers, networks and volumes (the database, uploaded
# files and the harness's Claude sign-in), the Docker images it uses, and the rebuild helper's service.
# The folder itself is left for you to delete. Run on the server: sudo ./host/uninstall.sh
#   --keep-images   keep the Docker images, so installing again is faster
set -euo pipefail
[ "$(id -u)" -eq 0 ] || { echo "Run it with sudo." >&2; exit 1; }

keep_images=0
for arg in "$@"; do
  case "$arg" in
    --keep-images) keep_images=1 ;;
    *) echo "Usage: sudo ./host/uninstall.sh [--keep-images]" >&2; exit 1 ;;
  esac
done

root=$(cd "$(dirname "$0")/.." && pwd)
cd "$root"
# Compose's project name: COMPOSE_PROJECT_NAME in .env, otherwise the folder's name.
project=$(sed -n 's/^COMPOSE_PROJECT_NAME=//p' .env 2>/dev/null | tail -n 1 | tr -d '"' || true)
[ -n "$project" ] || project=$(basename "$root" | tr '[:upper:]' '[:lower:]' | tr -cd 'a-z0-9_-')

images=""
[ $keep_images -eq 1 ] || images=", and the Docker images it uses"
echo "This deletes the Tornado Engine install \"$project\" in $root:"
echo "its containers, its database, uploaded files and the harness's Claude sign-in$images."
echo "It can't be undone. The folder itself stays."
read -r -p "Type $project to continue: " answer
[ "$answer" = "$project" ] || { echo "Nothing was changed."; exit 1; }

rmi=(--rmi all)
[ $keep_images -eq 0 ] || rmi=()
# Normally from the folder's compose file; if that can't be read (no .env, say), by the project name alone.
docker compose -p "$project" down --volumes --remove-orphans "${rmi[@]}" \
  || (cd / && docker compose -p "$project" down --volumes --remove-orphans "${rmi[@]}")

"$root/host/install.sh" --remove

echo "Uninstalled. To finish, delete the folder: rm -rf \"$root\""
