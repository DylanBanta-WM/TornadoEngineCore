# Tornado Engine

Tools for school IT teams, built privacy first.

## Privacy first

1. The AI assistant never runs on the host. It runs in a harness container with only its own workspace, and reaches real data only through the privacy broker, as pseudonyms.
2. Every piece of stored data has a classification, and anything unclassified is never shown to the assistant.
3. Credentials and settings are entered by an admin on the Administration pages, not in `.env`.
4. Logs are scrubbed as they're written.
5. Every broker call is audited.

## Pre-run setup

Do this before running `docker compose up -d` for the first time.

1. Copy the settings file:

   ```bash
   cp .env.example .env
   ```

2. In `.env`, set:
   - `APP_KEY`: the output of `echo "base64:$(openssl rand -base64 32)"`.
   - `DB_PASSWORD`: a long random password, for example from `openssl rand -base64 32`.
   - `WEB_BIND`: the address your Nginx server uses to reach Tornado Engine.
     - `127.0.0.1` if Nginx runs on this machine.
     - This server's IP, for example `10.0.0.30`, if your Nginx server is a different machine.

     If you leave it at `0.0.0.0`, any computer on the network can reach the site directly, bypassing Nginx and HTTPS.
   - `WEB_PORT`: only if port 8090 is already in use.
   - `HOST_UID` and `HOST_GID`: only if `id -u` and `id -g` aren't 1000.

3. Start Tornado Engine:

   ```bash
   docker compose up -d
   ```

To change `WEB_BIND` later, edit `.env` and run `docker compose up -d` again.

## Running

```bash
docker compose up -d                                 # build, start and update
docker compose down                                  # stop
docker compose exec harness harness-claude           # run Claude Code in the harness
```

### Harness internet access

All three options are safe: the assistant only ever sees student and staff data through the privacy broker, as pseudonyms. Logged and Strict add security on top of that.

- **Open** (default): the assistant can reach any public website.
- **Logged**: the same as Open, but every site it connects to is recorded for you to review (`docker logs tornado-harness-proxy`).
- **Strict**: only approved sites (Anthropic, GitHub, package downloads, listed in `harness/proxy/allow.txt`). Web search still works, but the assistant can't open other pages.

Set `HARNESS_NETWORK=open`, `logged` or `strict` in `.env`, then run `docker compose up -d`.
