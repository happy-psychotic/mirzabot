# CLAUDE.md — AntiBan / Mirzabot Project

## What This Project Is
Mirzabot is a PHP Telegram bot for selling VPN services. It includes:
- Telegram bot webhook logic (main bot + reseller bots)
- VPN panel integration layer (Marzban, Marzneshin, x-ui, Alireza, Hiddify, etc.)
- Legacy `/panel` web admin and `/app` mini-app have been removed from this fork
- Token-authenticated JSON APIs under `/api`
- Cron-driven background jobs under `/cronbot`
- Reseller bot instances under `/vpnbot/Default/` (template) and `/vpnbot/<botname>/` (live copies)

---

## ⛔ DEPLOY RULES — NON-NEGOTIABLE

### The only correct deploy method
1. Make all changes locally
2. Commit and push to git (`git push origin main`)
3. Run the deploy script:
```bash
bash /home/saeid/Documents/AntiBan/scripts/deploy_antibot_from_local.sh
```
The script uses `git archive` (only committed code goes to server), rsync with excludes (config.php and runtime files are never touched), runs `php -l` validation, and auto-syncs all reseller bot instances.

`DRY_RUN=1 bash scripts/deploy_antibot_from_local.sh` to preview what will change.

### NEVER run any git command on the live server
No `git pull`, `git stash`, `git checkout`, `git reset`, `git merge` — nothing.
The server is a **deploy target only**, not a git client.
The server has local modifications (config, runtime data) that will always conflict with git operations.
Running git on the server WILL overwrite real config files with template placeholders and break all bots.

### NEVER use scp or rsync manually toward the server
The deploy script handles everything safely. Manual scp/rsync can accidentally overwrite `config.php` or miss the reseller bot sync step.

### Always commit before deploying
The deploy script uses `git archive` — only committed changes reach the server. Uncommitted edits are never deployed.

---

## ⛔ FILES THAT MUST NEVER BE OVERWRITTEN ON SERVER

These are server-local files. They are NOT in git. Never scp or rsync them from local to server:

| File | Why |
|------|-----|
| `/var/www/mirza_pro/config.php` | Real DB credentials + bot token |
| `/var/www/mirza_pro/vpnbot/*/config.php` | Each reseller bot's own credentials |
| `error_log`, `log.txt`, `cookie.txt` | Runtime artifacts |
| `cronbot/error_log`, `cronbot/log.txt` | Runtime artifacts |
| `api/log.txt`, `sub/cookie.txt` | Runtime artifacts |
| `storage/` | Runtime storage |

The repo's `config.php` is a **placeholder template** with `{database_url}` etc. It must never reach the server.

---

## Reseller Bots

- Template (tracked in git): `vpnbot/Default/`
- Also tracked: `vpnbot/update/` — used for new bot installs
- Live instances on VPS (NOT in git, gitignored):
  - `383340509Red_v2ray_bot`
  - `7356499248anti_blocks_bot`
  - `96813594Anti_filternetbot`

When changing `vpnbot/Default/admin.php` or `vpnbot/Default/index.php`:
1. Also apply to `vpnbot/update/` (they stay in sync)
2. Deploy to all 4 live instances via `scp`
3. Run `php -l` on each deployed file

---

## SSH Alias
- Production VPS: `ssh antibot`
- App path: `/var/www/mirza_pro`

---

## Key Files
- `index.php` — main bot webhook entry
- `admin.php` — all admin flows (large, stateful)
- `keyboard.php` — keyboard button layout and filtering
- `function.php` — core helper library
- `panels.php` — VPN panel abstraction (`ManagePanel` class)
- `table.php` — DB schema and migrations
- `text.json` — bot text strings (also overridden by `textbot` DB table)
- `AGENTS.md` — full architecture runbook (read for deep context)

---

## Before Changing Code
- Bot text: check if it comes from `text.json`, `textbot` DB table, or hardcoded Persian strings.
- Admin buttons/flows: changes to `admin.php` affect both main and reseller bots — check both.
- Keyboard changes: `keyboard.php` controls which buttons show and when — changes affect all users.
- Panel integration: inspect `panels.php` + the specific adapter file + relevant invoice/product fields.
- Cron behavior: inspect `cronbot/` scripts + `function.php` + `install.sh`.
- Schema changes: add migration-safe logic to `table.php`.

---

## Before Deploying
1. `php -l` every changed PHP file locally
2. Identify exactly which files changed — only deploy those files
3. Use `scp` per file, not rsync toward server
4. After deploying, run `php -l` on the server copy to confirm
5. Check `tail -5 /var/www/mirza_pro/error_log` for new fatal errors

---

## .gitignore Key Patterns
```
/config.php.bak
/vpnbot/[0-9]*/          ← live reseller bot folders, never tracked
/vpnbot/*_bot/           ← live reseller bot folders, never tracked
/vpnbot/Default/config.php
/vpnbot/update/config.php
/error_log
/cronbot/error_log
/log.txt
/storage/
/docs/
```

---

## Testing
- Run `php -l <file>` on every changed PHP file before deploying.
- Automated tests required for: business flows, panel adapters, shared helpers, schema changes, auth/security, payment/invoice state, subscription/config output, reseller bot flows.
- See `docs/testing.md` for the full testing guide.

---

## Communication
- Always respond in English only.
