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
    # Build next to the current styles (not emptying them first), so pages keep their styles
    # while it builds; then remove the files the new build doesn't use.
    if npx vite build --emptyOutDir=false >> "$dir/assets.log" 2>&1; then
      echo ok > "$dir/assets.done"
      sleep 30
      node -e "const fs = require('fs'), dir = 'public/build/';
        const keep = new Set(Object.values(JSON.parse(fs.readFileSync(dir + 'manifest.json'))).flatMap(e => [e.file, ...(e.css || []), ...(e.assets || [])]));
        for (const f of fs.readdirSync(dir + 'assets')) if (! keep.has('assets/' + f)) fs.unlinkSync(dir + 'assets/' + f);" >> "$dir/assets.log" 2>&1
    else
      echo failed > "$dir/assets.done"
    fi
  fi
  sleep 3
done
