# The harness's only way out: a proxy that allows the sites in allow.txt.
FROM debian:trixie-20261020-slim

RUN apt-get update && apt-get install -y --no-install-recommends tinyproxy \
    && rm -rf /var/lib/apt/lists/*

COPY harness/proxy/tinyproxy.conf /etc/tinyproxy/tinyproxy.conf
COPY harness/proxy/allow.txt /etc/tinyproxy/allow.txt

USER tinyproxy
EXPOSE 8888
CMD ["tinyproxy", "-d", "-c", "/etc/tinyproxy/tinyproxy.conf"]
