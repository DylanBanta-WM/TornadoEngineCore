#!/bin/bash
# Runs in the harness once it's connected (harness-connect): checks in with the website every
# minute, and runs the prompts modules queue for Claude Code (App\Harness\HarnessTask), one at a
# time, sending back what Claude Code prints.
token_file="$HOME/.config/tornado/harness-token"
last_hello=0

api() { # api METHOD PATH [JSON]
  curl -fsS -m 30 -X "$1" -H "Authorization: Bearer $(cat "$token_file")" -H "Content-Type: application/json" \
    ${3:+--data-binary "$3"} "$HARNESS_WEB_URL/api/harness$2"
}

run_task() {
  local task=$1 id dir fifo errors args pid line answer exit
  id=$(jq -r .id <<<"$task")
  dir="/workspace/$(jq -r '.cwd // "web"' <<<"$task")"
  fifo=$(mktemp -u /tmp/task.XXXXXX) && mkfifo "$fifo"
  errors=$(mktemp /tmp/task-errors.XXXXXX)

  args=(-p --output-format stream-json --verbose --permission-mode "$(jq -r .permission_mode <<<"$task")")
  [ "$(jq -r '.session_id // empty' <<<"$task")" ] && args+=(--resume "$(jq -r .session_id <<<"$task")")
  [ "$(jq -r '.system_prompt // empty' <<<"$task")" ] && args+=(--append-system-prompt "$(jq -r .system_prompt <<<"$task")")
  [ "$(jq -r '.permission_prompt_tool // empty' <<<"$task")" ] && args+=(--permission-prompt-tool "$(jq -r .permission_prompt_tool <<<"$task")")

  (cd "$dir" && jq -rj .prompt <<<"$task" | harness-claude "${args[@]}" >"$fifo" 2>"$errors") &
  pid=$!

  while IFS= read -r line; do
    answer=$(api POST "/tasks/$id/events" "$(jq -cn --arg line "$line" '{lines: [$line]}')") || continue
    if [ "$(jq -r .stop <<<"$answer")" = "true" ]; then
      pkill -P "$pid" 2>/dev/null; kill "$pid" 2>/dev/null
    fi
  done <"$fifo"

  wait "$pid"; exit=$?
  api POST "/tasks/$id/finish" "$(jq -cn --argjson exit "$exit" --arg error "$(tail -c 2000 "$errors")" '{exit: $exit, error: $error}')" >/dev/null
  rm -f "$fifo" "$errors"
}

while true; do
  if [ -s "$token_file" ]; then
    if [ $(( $(date +%s) - last_hello )) -ge 60 ]; then
      api POST /hello >/dev/null || echo "harness: can't reach $HARNESS_WEB_URL" >&2
      last_hello=$(date +%s)
    fi

    task=$(api POST /tasks/next 2>/dev/null | jq -c '.task // empty' 2>/dev/null)
    if [ -n "$task" ]; then
      run_task "$task"
      continue
    fi
  fi
  sleep 2
done
