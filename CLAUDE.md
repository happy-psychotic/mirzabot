# CLAUDE.md — AntiBan / Mirzabot Project

## What This Project Is
Mirzabot is a PHP Telegram bot for selling VPN services. It includes:
- Telegram bot webhook logic (main bot + reseller bots)
- VPN panel integration layer (Marzban, Marzneshin, x-ui, Alireza, Hiddify, etc.)
- Admin web panel under `/panel`
- Telegram mini-app frontend under `/app` (compiled, do not hand-edit)
- Token-authenticated JSON APIs under `/api`
- Cron-driven background jobs under `/cronbot`
- Reseller bot instances under `/vpnbot/Default/` (template) and `/vpnbot/<botname>/` (live copies)

## CRITICAL: Files That Must Never Be Overwritten
These files are server-specific runtime/config files. **Never overwrite them during deploy:**
- `config.php` on any live server — contains real DB credentials and bot tokens
- `vpnbot/*/config.php` — each reseller bot has its own config
- `error_log`, `log.txt`, `cookie.txt`, `api/log.txt`
- `storage/` directory
- `sub/cookie.txt`
- Any file matched by `.gitignore`

The repo `config.php` is a placeholder template. The real one lives only on the server.

## Deploy Rules — Read Before Every Deploy
1. **Always make changes locally first**, then deploy. Never edit files directly on the server unless it is an emergency outage.
2. **Always use the deploy script** at `scripts/deploy_antibot_from_local.sh` — it uses `rsync` and explicitly preserves `config.php` and runtime files.
3. **Never run `git pull` on the live server** — the server has local modifications (config, runtime data) that will conflict. Treat the server as a deploy target, not a git client.
4. **Never run `git stash` + `git pull` on the server** — this can expose the template `config.php` and break the bot.
5. Before high-risk changes (schema, installer, panel adapters), do a manual backup of `/var/www/mirza_pro`.
6. After deploying to `vpnbot/Default/`, check if other reseller bots (`vpnbot/*_bot/`) need the same file — they usually share identical copies.

## Reseller Bots
- Template: `/vpnbot/Default/`
- Live instances on VPS: `383340509Red_v2ray_bot`, `7356499248anti_blocks_bot`, `96813594Anti_filternetbot`
- All instances share the same `admin.php` and `index.php` — when you update `Default/`, deploy to all instances.
- Deploy with: `scp vpnbot/Default/admin.php antibot:/var/www/mirza_pro/vpnbot/<name>/admin.php`

## SSH Alias
- Production VPS: `ssh antibot`
- App path: `/var/www/mirza_pro`

## Key Files
- `index.php` — main bot webhook entry
- `admin.php` — all admin flows (large, stateful)
- `keyboard.php` — keyboard definitions
- `function.php` — core helper library
- `panels.php` — VPN panel abstraction (`ManagePanel` class)
- `table.php` — DB schema and migrations
- `text.json` — bot text strings (also overridden by `textbot` DB table)
- `AGENTS.md` — detailed architecture runbook (read this for deep context)

## .gitignore — Important Patterns
```
/config.php.bak
/vpnbot/[0-9]*/
/vpnbot/*_bot/
/error_log
/log.txt
/storage/
```
Live reseller bot folders are gitignored — do not try to track them.

## Before Changing Code
- Bot text: check if it comes from `text.json`, the `textbot` DB table, or hardcoded Persian strings.
- Admin buttons/flows: changes to `admin.php` affect both main and reseller bots — check both.
- Panel integration: inspect `panels.php` + the specific adapter file + relevant invoice/product fields.
- Cron behavior: inspect `cronbot/` scripts + `function.php` + `install.sh`.
- Schema changes: add migration-safe logic to `table.php`.

## Testing
- Run `php -l <file>` on every changed PHP file before deploying.
- Full automated tests are not required for every change. They are required for: business flows, panel adapters, shared helpers, schema/default data, auth/security, payment/invoice state, subscription/config output, reseller bot flows.
- See `docs/testing.md` for the full testing guide.

## Communication
- Always respond in English only.
