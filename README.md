# Tornado Engine

Tools for school IT teams, built privacy first.

## Privacy first

1. The AI assistant never runs on the host. It runs in a harness container with only its own workspace, and reaches real data only through the privacy broker, as pseudonyms.
2. Every piece of stored data has a classification, and anything unclassified is never shown to the assistant.
3. Credentials and settings are entered by an admin on the Administration pages, not in `.env`.
4. Logs are scrubbed as they're written.
5. Every broker call is audited.

## Pre-run setup

From [Pre-run Setup.md](Pre-run%20Setup.md). Do this before running `bin/web up` for the first time.

1. Copy the settings file:

   ```bash
   cp web/.env.example web/.env
   ```

2. In `web/.env`, set:
   - `DB_PASSWORD`: a long random password, for example from `openssl rand -base64 32`.
   - `WEB_BIND`: the address your Nginx server uses to reach Tornado Engine.
     - `127.0.0.1` if Nginx runs on this machine.
     - This server's IP, for example `10.0.0.30`, if your Nginx server is a different machine.

     If you leave it at `0.0.0.0`, any computer on the network can reach the site directly, bypassing Nginx and HTTPS.
   - `WEB_PORT`: only if port 8090 is already in use.

3. Start the website:

   ```bash
   bin/web up
   ```

To change `WEB_BIND` later, edit `web/.env` and run `bin/web up` again.

## Website

```bash
bin/web up      # build, start and migrate
bin/web down    # stop
```

## Harness

```bash
bin/harness up       # build and start
bin/harness connect  # connect to the website (the setup page shows the command)
bin/harness claude   # run Claude Code inside it
bin/harness down     # stop
```

### Internet access

All three options are safe: the assistant only ever sees student and staff data through the privacy broker, as pseudonyms. Logged and Strict add security on top of that.

- **Open** (default): the assistant can reach any public website.
- **Logged**: the same as Open, but every site it connects to is recorded for you to review (`docker logs tornado-harness-proxy`).
- **Strict**: only approved sites (Anthropic, GitHub, package downloads, listed in `harness/proxy/allow.txt`). Web search still works, but the assistant can't open other pages.

Set `HARNESS_NETWORK=open`, `logged` or `strict` in `harness/.env`, then run `bin/harness up`.
