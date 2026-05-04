# Mirzabot Agent Manual

## Purpose
This file is the working manual for future agent sessions in this repository. It describes what the project is, how it is structured, what is safe to change, what is runtime data, and how updates should be handled locally and on servers.

## Repository Identity
- Local repo path: `/home/saeid/Documents/AntiBan`
- Fork remote: `origin = git@github-happy:happy-psychotic/mirzabot.git`
- Upstream remote: `upstream = https://github.com/mahdiMGF2/mirzabot.git`
- This repo should use local git identity only:
  - `user.name = HappyPsychotic`
  - `user.email = coldsun1997@gmail.com`

## High-Level Product
Mirzabot is a PHP Telegram bot for selling VPN services. It includes:
- Telegram bot webhook logic
- VPN panel integration layer for multiple panel types
- Admin web panel
- Telegram mini-app frontend under `app/`
- Token-authenticated JSON APIs under `api/`
- Cron-driven background jobs
- Installer/update script

The codebase is monolithic PHP with a database-first design. Many behaviors are controlled from database tables, especially `setting`, `textbot`, `marzban_panel`, `product`, and `invoice`.

## Main Entrypoints
- [index.php](/home/saeid/Documents/AntiBan/index.php)
  - Main Telegram bot webhook entry.
  - Loads config, helpers, keyboard definitions, DB-backed texts, and panel manager.
  - Handles user bootstrap, anti-spam, user state machine, channel checks, purchases, renewals, etc.
- [table.php](/home/saeid/Documents/AntiBan/table.php)
  - Schema bootstrap and migration script.
  - Creates tables if missing and backfills columns using `addFieldToTable(...)`.
  - Must be treated as the canonical schema/migration layer.
- [function.php](/home/saeid/Documents/AntiBan/function.php)
  - Core helper library.
  - Contains DB utilities, cron helpers, shell/crontab helpers, QR helpers, misc business logic, and many shared functions used everywhere.
- [panels.php](/home/saeid/Documents/AntiBan/panels.php)
  - Abstraction layer for all supported VPN backends.
  - `ManagePanel` is the main orchestration class for create/read/extend/disable user operations.
- [install.sh](/home/saeid/Documents/AntiBan/install.sh)
  - Interactive installer/update/removal script.
  - Important: this script still contains naming/paths from the "pro" line and should be reviewed carefully before broad installer edits.

## Major Areas

### Bot Runtime
- Files:
  - [index.php](/home/saeid/Documents/AntiBan/index.php)
  - [admin.php](/home/saeid/Documents/AntiBan/admin.php)
  - [keyboard.php](/home/saeid/Documents/AntiBan/keyboard.php)
  - [botapi.php](/home/saeid/Documents/AntiBan/botapi.php)
  - [request.php](/home/saeid/Documents/AntiBan/request.php)
  - [webhooks.php](/home/saeid/Documents/AntiBan/webhooks.php)
- Behavior:
  - Telegram updates are processed directly in `index.php`.
  - Admin flows are large and stateful, mostly in `admin.php`.
  - Text content is partly loaded from `text.json` and heavily overridden/augmented by DB table `textbot`.

### VPN Panel Adapters
- Files:
  - [Marzban.php](/home/saeid/Documents/AntiBan/Marzban.php)
  - [marzneshin.php](/home/saeid/Documents/AntiBan/marzneshin.php)
  - [x-ui_single.php](/home/saeid/Documents/AntiBan/x-ui_single.php)
  - [alireza_single.php](/home/saeid/Documents/AntiBan/alireza_single.php)
  - [hiddify.php](/home/saeid/Documents/AntiBan/hiddify.php)
  - [WGDashboard.php](/home/saeid/Documents/AntiBan/WGDashboard.php)
  - [s_ui.php](/home/saeid/Documents/AntiBan/s_ui.php)
  - [ibsng.php](/home/saeid/Documents/AntiBan/ibsng.php)
  - [mikrotik.php](/home/saeid/Documents/AntiBan/mikrotik.php)
- Notes:
  - `ManagePanel` selects behavior based on `marzban_panel.type`.
  - Product-level overrides such as `inbounds`, `proxies`, and reset strategy can change adapter behavior.
  - Subscription URLs and config extraction logic vary by backend.

### Admin Web Panel
- Directory: `/panel`
- Main files:
  - [panel/index.php](/home/saeid/Documents/AntiBan/panel/index.php)
  - [panel/login.php](/home/saeid/Documents/AntiBan/panel/login.php)
  - [panel/header.php](/home/saeid/Documents/AntiBan/panel/header.php)
- Notes:
  - Old-style PHP pages rendered server-side.
  - Session-based login.
  - Login is additionally gated by `setting.iplogin`.
  - Passwords are compared as plain strings in current code. Treat auth-related edits as sensitive.

### Mini-App Frontend
- Directory: `/app`
- Entry:
  - [app/index.php](/home/saeid/Documents/AntiBan/app/index.php)
- Notes:
  - This is a compiled frontend build, not source TypeScript/React code.
  - Files under `app/assets/` are build artifacts and should not be hand-edited unless there is no source repo available.
  - Telegram Web App client script is loaded from `app/js/telegram-web-app.js`.

### JSON API
- Directory: `/api`
- Representative files:
  - [api/users.php](/home/saeid/Documents/AntiBan/api/users.php)
  - [api/service.php](/home/saeid/Documents/AntiBan/api/service.php)
  - [api/settings.php](/home/saeid/Documents/AntiBan/api/settings.php)
  - [api/verify.php](/home/saeid/Documents/AntiBan/api/verify.php)
- Notes:
  - Token-authenticated over header `Token`.
  - Some endpoints accept either `hash.txt` token or bot API key.
  - API style is inconsistent: some files have newer helper structure, some older procedural style.
  - [api/index.php](/home/saeid/Documents/AntiBan/api/index.php) is currently empty.

### Subscription Endpoint
- [sub/index.php](/home/saeid/Documents/AntiBan/sub/index.php)
- Notes:
  - Maps `/sub/<invoice_id>` to live config output by fetching invoice + panel data.
  - Returns plain text subscription/config bundle.

### Cron Jobs
- Directory: `/cronbot`
- Examples:
  - `NoticationsService.php`
  - `payment_expire.php`
  - `expireagent.php`
  - `uptime_node.php`
  - `uptime_panel.php`
  - `backupbot.php`
- Notes:
  - Cron wiring is partly generated/managed through PHP logic in `function.php` and installer logic.
  - Cron status flags also exist in DB settings.

## Database Model
Schema is managed in [table.php](/home/saeid/Documents/AntiBan/table.php). Core tables include:
- `user`
- `setting`
- `admin`
- `help`
- `channels`
- `marzban_panel`
- `product`
- `invoice`
- `Payment_report`
- `Discount`
- `DiscountSell`
- `PaySetting`
- `service_other`
- `shopSetting`
- `topicid`
- `textbot`
- `logs_api`
- `app`
- `botsaz`
- `reagent_report`
- plus several support/reporting tables

Important practical rule:
- Any feature change touching business logic usually also touches DB-backed settings, product rules, or invoice state transitions. Always inspect `table.php` and relevant table reads/writes before changing logic.

## Configuration and Secrets
- Main config file: [config.php](/home/saeid/Documents/AntiBan/config.php)
- Contains:
  - DB host/name/user/password
  - Telegram bot token
  - admin numeric ID
  - domain
  - bot username
- Current file in repo is a template-like placeholder form. On real servers it becomes environment-specific and must not be overwritten blindly.

## Runtime and Data Boundaries
These should be treated as server-local/runtime data, not source of truth:
- `config.php` on live server
- `error_log`
- `log.txt`
- `cookie.txt`
- `api/log.txt`
- `storage/`
- `sub/cookie.txt`
- generated bot folders under `vpnbot/`
- DB contents
- active crontab

If these exist on server, preserve them during deploy/update.

## Current Deployment Reality
Known production bot server:
- Host alias: `antibot`
- App path: `/var/www/mirza_pro`
- Server should not be treated as an internet-connected git client.
- Preferred deployment model is local-to-server upload over SSH.

Known deploy script:
- [scripts/deploy_antibot_from_local.sh](/home/saeid/Documents/AntiBan/scripts/deploy_antibot_from_local.sh)

What the current deploy script does:
- builds a clean tree from local git using `git archive`
- uploads tracked project files from local to server via `rsync`
- preserves server-local files such as `config.php` and runtime artifacts
- does not take a full backup on every deploy
- runs basic PHP syntax validation on `index.php` and `config.php` on the server

## Update Architecture
Use this repo as the source of truth. Do not treat direct server edits as authoritative.

### Branching
- `main`: deployable branch
- `custom/<topic>`: feature/fix branches

### Normal Local Flow
```bash
git checkout -b custom/<topic>
# edit locally
git add -A
git commit -m "<message>"
git push -u origin custom/<topic>
```

### Sync Upstream Changes
```bash
git fetch upstream
git checkout main
git merge upstream/main
git push origin main
```

If custom work exists:
```bash
git checkout custom/<topic>
git rebase main
git push --force-with-lease
```

### Server Deploy Flow
```bash
/home/saeid/Documents/AntiBan/scripts/deploy_antibot_from_local.sh
```

## Safe Editing Rules For Future Agents
- Prefer editing local code, then deploy.
- Never overwrite live `config.php` intentionally unless task is explicitly config-related.
- Do not assume the server can `git fetch` from GitHub.
- Before touching installer logic, inspect all hard-coded paths and repo URLs. This repo still has mixed naming from older/pro variants.
- Before changing any cron behavior, inspect both `cronbot/` scripts and cron creation logic in `function.php` and `install.sh`.
- Before changing panel integration behavior, inspect:
  - `panels.php`
  - the specific panel adapter file
  - invoice and product fields used by that path
- Before changing admin UX or bot text behavior, inspect whether the text comes from:
  - `text.json`
  - `textbot` DB table
  - hard-coded Persian strings

## Known Codebase Characteristics
- Mixed procedural and object-oriented PHP.
- Inconsistent naming and casing across files and DB fields.
- English/Persian mixed literals.
- Some endpoints have older duplicated helper patterns instead of shared abstractions.
- The frontend in `app/assets/` is compiled output, not maintainable source.
- There are legacy artifacts in `/panel/assets`, `/panel/img`, and similar folders that are not central to backend logic.

## Known Risks
- Web panel login currently appears to compare plain-text password values.
- Installer/update logic contains mixed repo/path history and can be dangerous if changed casually.
- APIs are not uniformly structured and token handling varies.
- Database migrations are implicit in `table.php`; careless edits can break existing installs.
- Subscription output depends on live panel connectivity, not just DB data.

## Files Future Agents Should Read First
1. [AGENTS.md](/home/saeid/Documents/AntiBan/AGENTS.md)
2. [index.php](/home/saeid/Documents/AntiBan/index.php)
3. [function.php](/home/saeid/Documents/AntiBan/function.php)
4. [panels.php](/home/saeid/Documents/AntiBan/panels.php)
5. [table.php](/home/saeid/Documents/AntiBan/table.php)
6. [install.sh](/home/saeid/Documents/AntiBan/install.sh)

## Files Usually Safe To Ignore Initially
- `vendor/`
- compiled `app/assets/*`
- most image/font/static asset files

## When Making Changes
- Keep commits small and topic-specific.
- Prefer source changes over server hotfixes.
- Preserve backward compatibility where possible because this project updates existing live databases in place.
- If a change affects schemas, include migration-safe logic in `table.php`.
- If a change affects deploys, keep the update script and AGENTS doc aligned.
- Full backups are not required on every deploy. Take a manual backup only before high-risk changes such as schema changes, installer rewrites, panel adapter rewrites, or destructive file moves.
