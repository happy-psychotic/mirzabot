# Test Panel

This file documents disposable panel infrastructure for local development and pre-production checks. It is not part of the production bot app and must not be treated as a deployment target.

## Alireza x-ui On `antibot`

- Host alias: `antibot`
- Server path: `/usr/local/x-ui`
- Service: `x-ui`
- Version: `1.10.2`
- Panel type in Mirzabot: `alireza_single`
- Panel URL for Mirzabot running on `antibot`: `http://127.0.0.1:18080/jnZHZujtGKZhaC`
- Public URL reported by x-ui: `http://185.250.41.22:18080/jnZHZujtGKZhaC/`
- Current access note: localhost on `antibot` works. Direct public access from the local workstation returned an empty response, so use an SSH tunnel if browser/API access is needed from local.
- Username: `HCuUnqoSW70D`
- Password: `VFv4eMq9ic0hjrrjn5`

SSH tunnel example:

```bash
ssh -L 18080:127.0.0.1:18080 antibot
```

Then open:

```text
http://127.0.0.1:18080/jnZHZujtGKZhaC/
```

## Disposable Inbound

- Inbound id: `1`
- Remark: `mirzabot-disposable-vless`
- Protocol: `vless`
- Port: `18081`
- Seed client email: `mirzabot_seed_client`
- Seed client uuid: `fcfc7a9c-e270-4dad-b955-8677da67b2b1`
- Seed client subId: `mirzabotseed`

Use this inbound only for disposable Alireza single integration tests. Do not put production customers on it.

## Verification Commands

Run these from local:

```bash
ssh antibot 'systemctl is-active x-ui && ss -ltnp | grep :18080'
```

Check the panel API from the server:

```bash
ssh antibot 'cookie=/tmp/xui-test-cookie.txt; rm -f "$cookie"; curl -sS -c "$cookie" -X POST http://127.0.0.1:18080/jnZHZujtGKZhaC/login -d "username=HCuUnqoSW70D&password=VFv4eMq9ic0hjrrjn5"; curl -sS -b "$cookie" http://127.0.0.1:18080/jnZHZujtGKZhaC/xui/API/inbounds/'
```

Run the disposable create/update/delete smoke test from `antibot`:

```bash
cd /var/www/mirza_pro
ALIREZA_XUI_BASE_URL='http://127.0.0.1:18080/jnZHZujtGKZhaC' \
ALIREZA_XUI_USERNAME='HCuUnqoSW70D' \
ALIREZA_XUI_PASSWORD='VFv4eMq9ic0hjrrjn5' \
ALIREZA_XUI_INBOUND_ID='1' \
php tests/Smoke/AlirezaXuiPanelSmoke.php
```

If running the smoke test before deployment, upload or copy only `tests/Smoke/AlirezaXuiPanelSmoke.php` to a temporary path on `antibot` and run it there. Do not deploy the whole bot just to run this smoke test.

## Cautions

- This panel is test-only.
- Do not edit `/var/www/mirza_pro` while maintaining this panel.
- Do not assume the server can fetch from GitHub. The current install was performed from a locally downloaded release archive uploaded to `/tmp`.
- If credentials or base path are rotated, update this file immediately.
