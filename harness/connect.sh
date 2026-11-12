#!/bin/sh
# Saves the token from the website's setup page: harness-connect <token>
set -eu
[ -n "${1:-}" ] || { echo "usage: harness-connect <token>" >&2; exit 2; }
mkdir -p "$HOME/.config/tornado"
umask 077
printf '%s' "$1" > "$HOME/.config/tornado/harness-token"
curl -fsS -m 10 -X POST -H "Authorization: Bearer $1" "$HARNESS_WEB_URL/api/harness/hello" >/dev/null \
  && echo "Connected." \
  || { echo "Saved, but the website didn't accept the token." >&2; exit 1; }
