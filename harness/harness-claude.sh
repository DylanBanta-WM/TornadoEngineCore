#!/bin/bash
# Starts Claude Code with the login saved on the website's setup page, and the MCP servers
# that modules give Claude (for example the broker), reached through nginx-harness.
set -euo pipefail

token_file="$HOME/.config/tornado/harness-token"

if [ -s "$token_file" ]; then
  token=$(cat "$token_file")

  if login=$(curl -fsS -m 10 -H "Authorization: Bearer $token" "$HARNESS_WEB_URL/api/harness/claude" 2>/dev/null); then
    credential=$(jq -r .credential <<<"$login")
    case "$(jq -r .type <<<"$login")" in
      subscription) export CLAUDE_CODE_OAUTH_TOKEN="$credential" ;;
      api_key) export ANTHROPIC_API_KEY="$credential" ;;
    esac
  fi

  # Register the modules' MCP servers (for example the broker) in Claude Code's user settings,
  # replacing last time's, so they work with every claude command.
  if servers=$(curl -fsS -m 10 -H "Authorization: Bearer $token" "$HARNESS_WEB_URL/api/harness/mcp" 2>/dev/null); then
    while IFS=$'\t' read -r name path; do
      [ -n "$name" ] || continue
      config=$(jq -nc --arg url "$HARNESS_WEB_URL$path" --arg auth "Bearer $token" '{type: "http", url: $url, headers: {Authorization: $auth}}')
      claude mcp remove --scope user "$name" >/dev/null 2>&1 || true
      claude mcp add-json --scope user "$name" "$config" >/dev/null
    done < <(jq -r '.servers | to_entries[] | [.key, .value] | @tsv' <<<"$servers")
  fi
fi

exec claude "$@"
