# Project Update Workflow

## Purpose
This document defines the update and deployment workflow for this repository.

## Mandatory Rule
- Every change starts on local repository.
- Server must receive changes only via deploy from local.
- Avoid direct server edits except emergency recovery when local-first flow is impossible.

The important constraint is:
- `antibot` does not reliably have internet access to GitHub

Because of that:
- do not rely on `git pull` on the server
- update locally first
- then deploy from local to server over SSH

## Source of Truth
- Local repo: `/home/saeid/Documents/AntiBan`
- Fork remote: `origin = git@github-happy:happy-psychotic/mirzabot.git`
- Original Mirza project: `upstream = https://github.com/mahdiMGF2/mirzabot.git`

`main` in your local repo is the source of truth for deployment.

## Runtime Files To Preserve
These are server-local and should not be overwritten during deploy:
- `config.php`
- `error_log`
- `log.txt`
- `cookie.txt`
- `api/log.txt`
- `storage/`
- `sub/cookie.txt`
- generated bot folders under `vpnbot/`

These are ignored in local git through [.gitignore](/home/saeid/Documents/AntiBan/.gitignore).

## Normal Change Workflow
For your own code changes:

```bash
cd /home/saeid/Documents/AntiBan
git checkout -b custom/<topic>
# edit files
git add -A
git commit -m "<message>"
git push -u origin custom/<topic>
```

If the branch is ready:

```bash
git checkout main
git merge custom/<topic>
git push origin main
```

## Updating From Original Mirza Project
When upstream Mirza changes:

```bash
cd /home/saeid/Documents/AntiBan
git fetch upstream
git log --oneline HEAD..upstream/main
```

If the incoming changes look acceptable:

```bash
git checkout main
git merge upstream/main
git push origin main
```

If you want to review more safely first:

```bash
git checkout -b review/upstream-<date>
git merge upstream/main
```

Then inspect conflicts, test, and merge that branch into `main`.

## Deploying To `antibot`
Use the local deploy script:

```bash
/home/saeid/Documents/AntiBan/scripts/deploy_antibot_from_local.sh
```

Optional explicit form:

```bash
/home/saeid/Documents/AntiBan/scripts/deploy_antibot_from_local.sh antibot /var/www/mirza_pro main
```

What it does:
- builds a clean deployment tree from local git with `git archive`
- uploads tracked files to `antibot` using `rsync` over SSH
- preserves runtime/local files listed above
- does not require GitHub access from the server
- does not make a full backup every deploy
- runs basic `php -l` syntax checks on the server after upload

## When To Take A Manual Backup
No need to take a full backup for every routine code deploy.

Take a manual backup before:
- database/schema changes in `table.php`
- large installer changes in `install.sh`
- major panel adapter rewrites
- risky file moves/removals
- deployment changes that affect runtime paths

Suggested manual backup examples:

```bash
ssh antibot 'cp -a /var/www/mirza_pro /root/mirza_manual_backup_$(date +%F_%H-%M-%S)'
```

Database dump example:

```bash
ssh antibot 'mysqldump -u <db_user> -p<db_pass> <db_name> > /root/mirza_db_$(date +%F_%H-%M-%S).sql'
```

## Recommended Update Routine
For ordinary maintenance:

1. `git fetch upstream`
2. review upstream commits
3. merge upstream into local `main`
4. make your own fixes if needed
5. `git push origin main`
6. deploy from local to server with `deploy_antibot_from_local.sh`

## Notes
- The old model of "server pulls from GitHub" is not the preferred workflow anymore.
- The server may still contain a `.git` directory, but deployment should not depend on it.
- If a future deploy needs migrations beyond file sync, run the required DB/bootstrap command explicitly after deploy.
