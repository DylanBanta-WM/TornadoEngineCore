# The harness: Claude Code and the development tools, nothing from the host.
FROM debian:trixie-20261020-slim

ARG CLAUDE_CODE_VERSION=2.1.322
ARG HARNESS_UID=1000
ARG HARNESS_GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        ca-certificates curl git gh jq less procps ripgrep python3 \
        nodejs npm \
        php8.4-cli php8.4-bcmath php8.4-curl php8.4-intl php8.4-mbstring \
        php8.4-mysql php8.4-sqlite3 php8.4-xml php8.4-zip composer \
    && rm -rf /var/lib/apt/lists/*

RUN npm install -g "@anthropic-ai/claude-code@${CLAUDE_CODE_VERSION}" && npm cache clean --force

RUN groupadd --gid "${HARNESS_GID}" claude \
    && useradd --uid "${HARNESS_UID}" --gid "${HARNESS_GID}" --create-home --shell /bin/bash claude

COPY harness/managed-settings.json /etc/claude-code/managed-settings.json
COPY --chmod=0755 harness/agent.sh /usr/local/bin/harness-agent
COPY --chmod=0755 harness/harness-claude.sh /usr/local/bin/harness-claude

USER claude
WORKDIR /workspace
CMD ["harness-agent"]
