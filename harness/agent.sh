#!/bin/sh
# Checks in with the website every minute once the harness is connected.
while true; do
  if [ -n "${HARNESS_TOKEN:-}" ]; then
    curl -fsS -m 10 -X POST -H "Authorization: Bearer $HARNESS_TOKEN" "$HARNESS_WEB_URL/api/harness/hello" >/dev/null \
      || echo "harness: can't reach $HARNESS_WEB_URL" >&2
  fi
  sleep 60
done
