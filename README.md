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

The harness only reaches the sites listed in `harness/proxy/allow.txt`.
