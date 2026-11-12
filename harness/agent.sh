#!/bin/sh
# Checks in with the website every minute once the harness is connected (harness-connect).
token_file="$HOME/.config/tornado/harness-token"
while true; do
  if [ -s "$token_file" ]; then
    curl -fsS -m 10 -X POST -H "Authorization: Bearer $(cat "$token_file")" "$HARNESS_WEB_URL/api/harness/hello" >/dev/null \
      || echo "harness: can't reach $HARNESS_WEB_URL" >&2
  fi
  sleep 60
done
