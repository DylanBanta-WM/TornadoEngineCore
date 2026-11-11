#!/bin/sh
# Writes the proxy config for HARNESS_NETWORK (open, logged or strict) and starts tinyproxy.
set -eu
mode="${HARNESS_NETWORK:-open}"
conf=/run/tinyproxy/tinyproxy.conf

cat > "$conf" <<EOF
Port 8888
Listen 0.0.0.0
Timeout 600
MaxClients 50
ConnectPort 443
ConnectPort 80
FilterURLs Off
FilterType ere
EOF

case "$mode" in
  open)
    # Any public site; never the local network.
    echo "LogLevel Warning" >> "$conf"
    echo "Filter \"/etc/tinyproxy/deny-local.txt\"" >> "$conf" ;;
  logged)
    echo "LogLevel Connect" >> "$conf"
    echo "Filter \"/etc/tinyproxy/deny-local.txt\"" >> "$conf" ;;
  strict)
    echo "LogLevel Connect" >> "$conf"
    echo "Filter \"/etc/tinyproxy/allow.txt\"" >> "$conf"
    echo "FilterDefaultDeny Yes" >> "$conf" ;;
  *)
    echo "HARNESS_NETWORK must be open, logged or strict (got: $mode)" >&2; exit 1 ;;
esac

echo "harness network: $mode" >&2
exec tinyproxy -d -c "$conf"
