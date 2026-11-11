# Tornado Engine

Tools for school IT teams, built privacy first.

## Privacy first

1. The AI assistant never runs on the host. It runs in a harness container with only its own workspace, and reaches real data only through the privacy broker, as pseudonyms.
2. Every piece of stored data has a classification, and anything unclassified is never shown to the assistant.
3. Credentials and settings are entered by an admin on the Administration pages, not in `.env`.
4. Logs are scrubbed as they're written.
5. Every broker call is audited.

## Harness

```bash
bin/harness up       # build and start
bin/harness claude   # run Claude Code inside it
bin/harness down     # stop
```

### Internet access

All three options are safe: the assistant only ever sees student and staff data through the privacy broker, as pseudonyms. Logged and Strict add security on top of that.

- **Open** (default): the assistant can reach any public website.
- **Logged**: the same as Open, but every site it connects to is recorded for you to review (`docker logs tornado-harness-proxy`).
- **Strict**: only approved sites (Anthropic, GitHub, package downloads, listed in `harness/proxy/allow.txt`). Web search still works, but the assistant can't open other pages.

Set `HARNESS_NETWORK=open`, `logged` or `strict` in `harness/.env`, then run `bin/harness up`.
