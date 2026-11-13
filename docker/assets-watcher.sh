#!/bin/sh
# Rebuilds the website's styles when the updater asks, after it installs or updates modules or Core.
dir=storage/app/tornado
mkdir -p "$dir"
while true; do
  if [ -f "$dir/assets.request" ]; then
    rm -f "$dir/assets.request" "$dir/assets.done"
    if [ ! -d node_modules ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
      npm ci --no-audit --no-fund > "$dir/assets.log" 2>&1
    fi
    if npm run build >> "$dir/assets.log" 2>&1; then echo ok > "$dir/assets.done"; else echo failed > "$dir/assets.done"; fi
  fi
  sleep 3
done
