# Mirzabot Update Architecture

## Goal
Keep local and server deployments customized, while still being able to receive updates from upstream safely.

## Repository Model
- `origin`: your fork (`happy-psychotic/mirzabot`)
- `upstream`: original project (`happy-psychotic/mirzabot` if same source, otherwise set real upstream)

## Branch Strategy
- `main`: stable branch, always deployable.
- `custom/<topic>`: custom feature/fix branches.
- Optional: `server/<name>` for server-specific temporary work (never long-lived).

## Rules
- Never make direct hot edits on server as source of truth.
- Make code changes locally, commit, push, then deploy.
- Keep runtime/data files out of git (logs, cookies, storage, secrets).
- Keep server config backups before every update.

## Initial Remote Setup
```bash
git remote -v
git remote add upstream <UPSTREAM_REPO_URL>
git fetch upstream
```

## Custom Change Flow
```bash
git checkout -b custom/<topic>
# edit
git add -A
git commit -m "<message>"
git push -u origin custom/<topic>
```

## Bring Upstream Updates
```bash
git fetch upstream
git checkout main
git merge upstream/main
# or: git rebase upstream/main
git push origin main
```

## Rebase Custom Work
```bash
git checkout custom/<topic>
git rebase main
git push --force-with-lease
```

## Deployment Safety (Server)
1. Backup important files (`config.php`, db dump, runtime dirs).
2. Pull from `origin/main` or a tested release tag.
3. Restore/verify local config.
4. Run health checks.

## Server Backup Minimum
- `config.php`
- database dump
- runtime directories used by bot/panel
- cron definitions
