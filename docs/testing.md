# Testing Strategy

## Goal

Add tests only where they reduce real production risk. This project is a monolithic PHP bot with DB-backed behavior and live panel integrations, so useful tests should focus on business flows, payload construction, migrations, and integration boundaries.

## When A Change Needs Tests

Write or update tests when a change affects:

- purchase, renewal, extra volume, extra time, test-account, or reseller-bot purchase flows
- panel adapters or `ManagePanel`
- subscription/config generation, host rewriting, QR/config delivery, or revoke/reset-link behavior
- database schema or default values in `table.php`
- payment verification or invoice state transitions
- webhook source validation, authentication, admin login, or token checks
- cron jobs that send messages, change invoice/user state, or call external services
- deploy/update scripts that delete, preserve, or rewrite server files
- shared helpers used by more than one flow

Tests are usually not required for:

- text-only copy changes
- documentation-only changes
- unused payment gateways or inactive feature modules
- static image/font/assets changes
- local one-off scripts that do not affect deploy/runtime behavior

## Test Layers

### Syntax

Always run PHP syntax checks for touched PHP files:

```bash
php -l path/to/file.php
```

For broad backend changes:

```bash
find . -path './vendor' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

### Unit Tests

Use unit tests for pure or mostly pure logic:

- config/subscription parsing and host rewriting
- Alireza response normalization
- keyboard visibility decisions when dependencies can be mocked
- invoice state helper logic
- command construction for backup/deploy helpers

Recommended future layout:

```text
tests/
  Unit/
    ConfigRewriteTest.php
    AlirezaPayloadTest.php
    KeyboardVisibilityTest.php
  Integration/
    ManagePanelAlirezaTest.php
    ResellerPurchaseFlowTest.php
    BackupCronTest.php
  fixtures/
    alireza/
    subscriptions/
```

Current lightweight runner:

```bash
php tests/run.php
```

The test files set `MIRZABOT_TESTING=1` when they need to include project code without opening a real database connection. Tests must not require live cURL/network access unless they explicitly skip when the required extension or service is unavailable.

### Integration Tests

Use integration tests for logic that depends on DB rows or panel API responses:

- `ManagePanel::createUser()`
- `ManagePanel::DataUser()`
- `ManagePanel::Revoke_sub()`
- reseller bot purchase
- backup cron behavior

External services must be mocked first. Production bot and panel servers should only be inspected read-only unless explicitly approved.

### Smoke Tests

Before deploy, run focused smoke checks:

- `php -l` on touched PHP files
- create/purchase flow in a test DB or fake panel harness
- Alireza single create, fetch, revoke/reset-link, and delete against a disposable inbound/client
- reseller bot purchase against a disposable reseller bot
- backup cron dry run or diagnostic run that confirms `mysqldump`, zip, and Telegram upload separately

## Priority For This Fork

Start with the active production surface:

- Alireza single panel adapter
- main bot purchase flow
- reseller/agent bot purchase flow
- subscription/config output and host rewrite
- backup cron diagnostics
- deploy script preservation rules

Disposable Alireza x-ui panel details are documented in [docs/test-panel.md](/home/saeid/Documents/AntiBan/docs/test-panel.md). Use it for manual adapter smoke tests before production deploys that affect Alireza create/update/revoke behavior.

The reusable live-panel smoke test is:

```bash
ALIREZA_XUI_BASE_URL='http://127.0.0.1:18080/jnZHZujtGKZhaC' \
ALIREZA_XUI_USERNAME='...' \
ALIREZA_XUI_PASSWORD='...' \
ALIREZA_XUI_INBOUND_ID='1' \
php tests/Smoke/AlirezaXuiPanelSmoke.php
```

This test creates a disposable client, verifies it can be listed, updates traffic/enable fields, verifies the update, and deletes the client in cleanup. It is not part of `php tests/run.php` because it requires a live x-ui panel.

Delay tests for:

- unused payment gateways
- inactive panel adapters
- wheel/lottery/game features
- mini-app compiled assets

Inactive or low-priority features from the current admin screenshots:

- Plisio, NowPayments, Rial gateway 1/2/3, Aqaye Pardakht, Zarinpal, offline crypto, Star Telegram
- extra time, issue reporting, product category, time category, refund button
- representative request, rules, phone/contact identity checks, Iranian-number verification, identity by link
- config note, normal-user note, bulk buy, training category, representative group, contact display
- first-purchase wheel, representative lottery, test cron, orphan cleanup cron, volume cleanup cron, weekly lottery, chance group

## Required Documentation In Future Changes

When a change needs tests, the change summary should state:

- what test was added or updated
- what production risk it covers
- what was manually verified if automated coverage is not yet possible
- why tests were skipped, if skipped
