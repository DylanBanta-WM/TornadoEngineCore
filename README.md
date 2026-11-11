# Tornado Engine

Tools for school IT teams, built privacy first.

Started 2026-11-11 from a clean history. Nothing has been built yet.

## Privacy first

1. The AI assistant never runs on the host. It runs in a harness container with only its own workspace, and reaches real data only through the privacy broker, as pseudonyms.
2. Every piece of stored data has a classification, and anything unclassified is never shown to the assistant.
3. Credentials and settings are entered by an admin on the Administration pages, not in `.env`.
4. Logs are scrubbed as they're written.
5. Every broker call is audited.
