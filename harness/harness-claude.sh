#!/bin/bash
# Starts Claude Code with the login saved on the website's setup page.
set -euo pipefail

token_file="$HOME/.config/tornado/harness-token"

if [ -s "$token_file" ] \
  && login=$(curl -fsS -m 10 -H "Authorization: Bearer $(cat "$token_file")" "$HARNESS_WEB_URL/api/harness/claude" 2>/dev/null); then
  credential=$(jq -r .credential <<<"$login")
  case "$(jq -r .type <<<"$login")" in
    subscription) export CLAUDE_CODE_OAUTH_TOKEN="$credential" ;;
    api_key) export ANTHROPIC_API_KEY="$credential" ;;
  esac
fi

exec claude "$@"
