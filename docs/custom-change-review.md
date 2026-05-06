# Custom Change Review Against Upstream

Date: 2026-05-06

Compared local `HEAD` with `upstream/main` at `8256b04`.

## Scope

Local branch status at review time:

- Local `main` is ahead of `origin/main` by 11 commits.
- Local `main` contains 19 commits on top of upstream.
- Diff size: 21 files changed, 1419 insertions, 173 deletions.

Changed files:

- `.gitignore`
- `AGENTS.md`
- `PROJECT_UPDATE.md`
- `admin.php`
- `alireza_single.php`
- `cronbot/uptime_node.php`
- `cronbot/uptime_panel.php`
- `function.php`
- `index.php`
- `keyboard.php`
- `panel/login.php`
- `panels.php`
- `scripts/deploy_antibot_from_local.sh`
- `scripts/sync_reseller_templates.sh`
- `scripts/telegram_menu_button.php`
- `table.php`
- `vpnbot/Default/index.php`
- `vpnbot/Default/keyboard.php`
- `vpnbot/update/index.php`
- `vpnbot/update/keyboard.php`
- `x-ui_single.php`

## Commit List

- `52f689c` docs: add update and merge architecture runbook
- `2db1845` ops: add safe antibot update script for fork-based deploy
- `24dab9d` ops: switch to local-to-server deploy workflow and add update runbook
- `6d556fe` fix(webhook): support proxy client IP for Telegram validation
- `4cf1cf7` fix(alireza): handle missing client stats when loading user services
- `1e7ded3` fix(alireza): use canonical inbounds endpoint and handle non-json response
- `3b563fe` fix(ui): hide unavailable main and agent menu buttons
- `1f2fc53` fix(vpnbot): hide test button when no accessible test panel
- `ad6c992` fix(vpnbot): sync reseller templates and hide unavailable test menu globally
- `ed82fd2` fix(webhook): allow local proxy source IPs in telegram IP check
- `6d57716` fix(deploy): enforce web-readable permissions after upload
- `03ae427` fix(vpnbot): only list active reseller test panels
- `798697e` Rewrite generated config hosts to override IP
- `efb4aa5` fix(runtime): use temp files for panel cookies and QR assets
- `1de35bc` feat(telegram): add chat menu button helper
- `525a78b` fix(panel): remove web login ip gate
- `980a8b2` fix(uptime): probe panel URL before alerting disconnect
- `a04ab4f` fix(vpnbot): harden reseller bot provisioning and errors
- `32ef72a` fix(alireza): fallback to inbound update when addClient is broken

## Low-Risk Changes

These are unlikely to break unrelated application behavior.

- Documentation: `AGENTS.md`, `PROJECT_UPDATE.md`.
- Runtime ignore rules in `.gitignore` for logs, cookies, storage, generated reseller bot folders, and config backups.
- QR and WireGuard config temp file names moved from webroot-relative predictable names to `sys_get_temp_dir()` via `runtimeTempPath()`.
- `cronbot/uptime_node.php` fixes a clear variable typo: it checked `$nodes` even though the response variable is `$Getdnodes`.
- Reseller bot purchase error reporting now preserves useful failure messages instead of blindly JSON-encoding the `msg` field.
- `keyboard_config()` now handles configs without fragments and malformed vmess labels more defensively.

## Medium-Risk Changes

These changes are probably useful, but they touch shared behavior or runtime assumptions.

- Webhook IP validation now trusts `CF-Connecting-IP`, `X-Real-IP`, and the first `X-Forwarded-For` value, and allows private/local IPs. This is useful behind a reverse proxy, but it weakens PHP-layer Telegram source validation unless the edge proxy enforces it.
- `panel/login.php` removes the `setting.iplogin` gate. This may be intentional for usability, but it is a security behavior change.
- Main and reseller keyboards hide unavailable buttons based on DB state. This improves UX, but it can hide a button because of incomplete `textbot`, `help`, `product`, or panel rows.
- `cronbot/uptime_panel.php` falls back from raw TCP check to an HTTP HEAD request. This reduces false alerts, but it changes alert timing and depends on URL reachability semantics.
- Deployment uses `git archive` and `rsync --delete` with a central exclude list for runtime files. It now supports `DRY_RUN=1` and no longer auto-syncs generated reseller bot templates.
- `scripts/sync_reseller_templates.sh` still exists, but deploy no longer runs it automatically. Use it manually only after reviewing generated reseller bot folders.

## High-Risk Changes

These changes can affect many flows or panel data.

- Host rewriting in `function.php`, `panels.php`, and `table.php` rewrites subscription/config output to `setting.config_host_override`, defaulting to `185.143.234.235`.
  - Current mitigation: rewriting is gated by `setting.config_host_rewrite_status`, defaults to `off`, and can be toggled from the admin feature-status menu.
  - Remaining risk: when enabled, it applies broadly across panel types and can alter all delivered configs, including old subscriptions.
  - Remaining risk: only some protocol formats are handled; unsupported formats may not rewrite correctly.
- Alireza single client discovery was substantially changed in `alireza_single.php` and `panels.php`.
  - Risk: it now combines client config and client stats from multiple API shapes and fallbacks.
  - Risk: when stats are missing, traffic/expiry can become zero even though the panel has real values.
  - Risk: `get_onlineclialireza()` returns before closing/unlinking the temp cookie file in several paths.
- Alireza `addClient` no longer falls back to updating the entire inbound after the `JSON_EXTRACT(client.value, '$.email')` error.
  - Current mitigation: the dangerous full-inbound fallback was removed.
  - Remaining risk: the original panel/API error can still happen and must be handled by diagnostics or a safer adapter-level fix later.
- `ManagePanel::Revoke_sub()` for `alireza_single` still modifies the client using a partial `settings.clients[0]` payload with only `id`, `enable`, and `subId`.
  - Risk: if Alireza/x-ui treats `updateClient` payloads as replacement rather than merge, this can erase fields such as email, flow, traffic, or expiry. This area directly matches the reported "reset link/config id made a panel row empty" symptom.

## Probably Unnecessary Or Optional Changes

These are candidates to remove or postpone if you want the fork to stay close to upstream.

- `scripts/telegram_menu_button.php`: useful only if the Telegram menu button is actively managed from CLI.
- `panel/login.php` IP-gate removal: convenient, but not required for the bot's purchase/reseller flows.
- Global config host rewriting: now feature-gated and default-off. Keep it off unless the deployment explicitly needs configs to expose `setting.config_host_override`.
- Main menu hiding based on missing text/help/product rows: can be useful, but may hide features while debugging.
- Uptime HTTP fallback: useful for false positives, but unrelated to purchase or reseller issues.

## Current Bugs Mentioned By User

### Alireza row became empty

Most relevant areas:

- `panels.php` Alireza `Revoke_sub()` path.
- `panels.php` Alireza `Modifyuser()` callers.
- `alireza_single.php` `updateClientalireza()`.
- removed full-inbound fallback code in `alireza_single.php`.

Initial assessment:

- This was likely related to partial update payloads or the full inbound fallback update. The full-inbound fallback has been removed, and Alireza modify now builds the update payload from the existing client before merging changes.
- Do not run these paths on production without a backup/export of the affected inbound.

### Reseller bot create error `307`

Most relevant areas:

- `vpnbot/Default/index.php`
- `vpnbot/update/index.php`
- `panels.php::createUser()`
- Alireza add/create paths

Initial assessment:

- The new reseller error handling exposes the error better; it probably did not create the `307`.
- Need to inspect production response body/logs to know whether `307` is an HTTP redirect, panel response code, or a bot-state value.

### Main bot timeout while creating config

Most relevant areas:

- `CurlRequest`
- panel URL connectivity
- `outputlink()`
- Alireza create/get subscription paths

Initial assessment:

- A 10-second cURL timeout can be network/panel load and not necessarily a code regression.
- The broad config host rewriting adds extra subscription fetch/parse work, but the reported error text looks like a network timeout from a panel request.
- Default `CurlRequest` timeout and subscription fetch timeout were increased from about 10/6 seconds to 15 seconds.

### Backup failure

Relevant file:

- `cronbot/backupbot.php`

Initial assessment:

- `cronbot/backupbot.php` is unchanged from upstream in this diff.
- The failure is probably runtime/environmental: `mysqldump` flags, DB credentials, missing `ZipArchive`, write permissions in `cronbot/`, or Telegram upload failure after dump.
- The current code only reports a generic failure and does not send stderr, so it needs better diagnostics before a fix.

## Recommended Next Tests

Highest priority:

- Unit tests for config host rewriting with vmess, vless/trojan with ports, ss, plain subscription bundles, base64 subscription bundles, empty/null values.
- Unit tests for Alireza payload normalization from `/xui/API/inbounds/`, missing `clientStats`, and fallback `/panel/api/inbounds/getClientTraffics`.
- Golden payload tests for Alireza `updateClient` and inbound fallback update. These must prove email, id, flow, limits, expiry, subId, and existing inbound fields are preserved.
- Reseller purchase flow test using fake panel responses for success, `307`, timeout, missing product code, and unsuccessful `createUser`.
- Backup command construction test or smoke script that captures stderr and verifies `mysqldump`, zip creation, and Telegram upload separately.

Progress after this review:

- Added lightweight unit coverage for config-host rewrite enabled/disabled behavior, Alireza payload extraction/normalization, default `CurlRequest` timeout, and deploy-script preservation rules.
- Created a disposable Alireza x-ui test panel on `antibot`; details are in [docs/test-panel.md](/home/saeid/Documents/AntiBan/docs/test-panel.md).
- Manual Alireza adapter smoke tests should use that test panel before any production deploy touching create/update/revoke behavior.

Do not spend time initially on inactive payment gateways, unused panel adapters, wheel/lottery, mini-app build artifacts, or web panel UI tests unless they become active again.

## Inactive Or Low-Priority Features From Screenshots

Based on the screenshots, do not spend initial review/test time on these unless they are turned back on:

- Payment gateways: Plisio, NowPayments, Rial gateway 1/2/3, Aqaye Pardakht, Zarinpal, offline crypto, Star Telegram.
- Store features: extra time, issue reporting, product category, time category, refund button.
- Bot/user gates: representative request, rules, contact-number identity check, Iranian-number verification, no-button glass mode, identity verification, identity by link.
- Content and marketing features: config note, normal-user note, bulk buy, training category, representative group, contact display.
- Game/lottery features: first-purchase wheel, representative lottery, test cron, orphan cleanup cron, volume cleanup cron, weekly lottery, chance group.

Active/high-priority surface from screenshots:

- Main bot status, user account button, new-user notification, private support, support in PV.
- Product buy, direct buy, extra volume, account disable status, product price display, config receive button.
- Card-to-card payment and manual receipt confirmation.
- Agent/reseller bot purchase and service viewing.
- Alireza single panel integration.
- Debt settlement, card-number copy, panel uptime cron, time warning cron, config keyboard.
