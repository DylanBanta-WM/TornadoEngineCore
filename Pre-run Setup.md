# Pre-run Setup

Do this before running `bin/web up` for the first time.

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
