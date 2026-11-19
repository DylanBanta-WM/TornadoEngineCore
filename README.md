# Tornado Engine

Tools for school IT teams, built privacy first.

AI coding tools like Claude Code, Codex and Copilot read the files around them for context. On a school district's server, those files can include student names, emails and passwords, which would then be sent to the AI company. Tornado Engine lets a district build its own tools with AI without that happening.

## How it works

- **The harness.** Claude runs in its own container and only sees one workspace: the website's code and its modules. It can't see the rest of the server.
- **The privacy broker.** When Claude needs directory data, the broker replaces people in the roles you choose (Students by default) with pseudonyms before Claude sees anything. Claude sees, for example:

  | Real                       | What Claude sees                  |
  |----------------------------|-----------------------------------|
  | `jane.doe@district.org`    | `p-3f9a1c2b7e@student`            |
  | `Jane Doe`                 | `[name p-3f9a1c2b7e]`             |
  | `Jane`, `Doe`              | `[given p-3f9a1c2b7e]`, `[family p-3f9a1c2b7e]` |
  | Student ID `204417`        | `[id p-3f9a1c2b7e-ab12]`          |
  | A phone number             | `[phone v-8d0e4f1a6c]`            |

  The same person always gets the same pseudonym, so Claude can still sort, count and build reports. When the answer is shown to people on the website, the broker turns every pseudonym back into the real value.
- **Modules.** Everything is a module, and a district installs only what it needs: for example GAM for Google Workspace, Access-Microsoft-AD and Access-Microsoft-Entra for Microsoft, and GitHub for sharing modules with other districts.
- **Chat.** Describe a tool in plain English and Claude builds it as a new module in your website, following the same rules. Attached spreadsheets, documents and images go through the broker too. Images have the names in their text covered in black, and faces and logos are pointed out so you can choose what to cover. This is careful but not guaranteed, so avoid uploading student information you don't need to.

## Privacy first

1. The AI assistant never runs on the host. It runs in a harness container with only its own workspace, and reaches real data only through the privacy broker, as pseudonyms.
2. Every piece of stored data has a classification, and anything unclassified is never shown to the assistant.
3. Credentials and settings are entered by an admin on the Administration pages, not in `.env`.
4. Logs are scrubbed as they're written.
5. Every broker call is audited.

## Pre-run setup

Do this before running `docker compose up -d` for the first time.

1. Make the settings file and install the rebuild helper (it lets the website rebuild its containers after installs and updates):

   ```bash
   sudo ./host/install.sh
   ```

   This also makes your `.env` file.

2. In `.env`, set:
   - `WEB_BIND`: the address your Nginx RP (reverse proxy) uses to reach Tornado Engine.
     - `127.0.0.1` if the Nginx RP runs on this machine.
     - This server's IP, for example `10.0.0.30`, if your Nginx RP is a different machine.

     If you leave it at `0.0.0.0`, any computer on the network can reach the site directly, bypassing the Nginx RP and HTTPS.
   - `WEB_PORT`: you only need to change this if port 8090 is already in use. Check with `ss -tulpn | grep :8090`
   - `APP_TIMEZONE`: your district's time zone, for example `America/Chicago`.
   - `HOST_UID` and `HOST_GID`: only if `id -u` and `id -g` aren't 1000.

3. Start Tornado Engine:

   ```bash
   docker compose up -d
   ```

To change `WEB_BIND` later, edit `.env` and run `docker compose up -d` again.

## First-time setup

Open the site in a browser. Setup asks, in order, for:

1. The site's web address.
2. How people sign in: Google, Microsoft or website passwords (and the keys for Google or Microsoft, if you choose them).
3. The admin account.
4. Which roles the broker gives pseudonyms (Students by default).
5. Connecting the harness, and signing Claude in.
6. The modules to install.

A short tour follows. To take it again, use the button on your profile page.

## Running

```bash
docker compose up -d                                 # build, start and update
docker compose down                                  # stop
docker compose exec harness harness-claude           # run Claude Code in the harness
```

## Modules

Core-modules, such as Nav, are installed automatically.

Install and update modules on the `/modules` page, and update Core under Administration → Updates. The `updater` service does the work. Some need the containers rebuilt; the website's Rebuild button does it, through the helper installed in Pre-run setup.

You can also list modules in `web/composer.local.json` under `require` and run `docker compose up -d`. Core's own files stay unchanged, so updating Core doesn't conflict with your modules.

To work on a module, clone its repo into `web/modules/`. That copy is used instead of the published one.

## Uninstalling

```bash
sudo ./host/uninstall.sh
```

This deletes the containers, the database, uploaded files, the Docker images and the rebuild helper. Add `--keep-images` to keep the Docker images. Then delete the folder.

### Harness internet access

All three options are safe: the assistant only ever sees student and staff data through the privacy broker, as pseudonyms. Logged and Strict add security on top of that.

- **Open** (default): the assistant can reach any public website.
- **Logged**: the same as Open, but every site it connects to is recorded for you to review (`docker compose logs harness-proxy`).
- **Strict**: only approved sites (Anthropic, GitHub, package downloads, listed in `harness/proxy/allow.txt`). Web search still works, but the assistant can't open other pages.

Set `HARNESS_NETWORK=open`, `logged` or `strict` in `.env`, then run `docker compose up -d`.
