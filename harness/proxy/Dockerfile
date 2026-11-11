# The harness's only way out. HARNESS_NETWORK picks open, logged or strict (start.sh).
FROM debian:trixie-20261020-slim

RUN apt-get update && apt-get install -y --no-install-recommends tinyproxy \
    && rm -rf /var/lib/apt/lists/*

COPY harness/proxy/allow.txt harness/proxy/deny-local.txt /etc/tinyproxy/
COPY --chmod=0755 harness/proxy/start.sh /usr/local/bin/start-proxy

USER tinyproxy
EXPOSE 8888
CMD ["start-proxy"]
