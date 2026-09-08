---
title: CI/CD Pipeline
parent: Technical Documentation
nav_order: 12
description: Automated tests and production deploy from GitHub to the RelayIQ cPanel server.
---

# CI/CD Pipeline

RelayIQ is a **Laravel + Inertia** app on cPanel. It cannot run on Vercel. Production is:

| Item | Value |
|------|--------|
| Live URL | https://relayiq.app |
| Git branch | `main` |
| cPanel git path | `/home/qkbghwib/relayiq.app` |
| Deploy trigger | `POST https://relayiq.app/deploy/agent` |

Do **not** use cPanel Git → Update for routine releases. That bypasses tests. GitHub Actions is the release gate.

```
feature branch  →  pull request  →  CI (lint, tests, build)
        ↓ merge
      main
        ↓ push (automatic)
  GitHub Actions
        ↓ tests must pass
  POST /deploy/agent
        ↓
  cPanel git fetch + reset origin/main
        ↓
  composer / migrate / caches / queue restart
        ↓
  GET /api/health  →  live
```

## What runs automatically

| Event | Workflow | Result |
|-------|----------|--------|
| Pull request or push to a feature branch | `CI` | Lint, PHPUnit, typecheck, Vite build. **No deploy.** |
| Push / merge to `main` (Laravel or deploy files) | `Deploy to production` | Same tests, then deploy `main` to https://relayiq.app, health check, rollback tag. |
| Manual **Run workflow** | `Deploy to production` | Same as production deploy, optional branch override. |
| Push to `develop` | `Deploy to staging` | Runs only after you set `STAGING_ENABLED=true`. Off by default. |

Broken tests **block** production. A developer cannot ship by pushing straight to GitHub without CI passing.

## One-time GitHub setup

Repo → **Settings → Secrets and variables → Actions**:

| Secret | Required | Value |
|--------|----------|--------|
| `DEPLOY_AGENT_KEY` | **Yes** | Same as server `DEPLOY_AGENT_KEY` or `DEPLOY_SECRET` in `LARAVEL_BACKEND/.env` |
| `DEPLOY_REMOTE_URL` | No | Defaults to `https://relayiq.app` |

Without `DEPLOY_AGENT_KEY`, the production workflow fails after tests on purpose.

Optional: **Settings → Environments → production**. Leave **required reviewers** off if you want merge-to-main to deploy with no extra click. Turn reviewers on only if you want a human approval gate.

Leave cPanel Git auto-deploy **disabled**. GitHub Actions already pulls `main` on the server after tests pass.

## Day-to-day

```powershell
git checkout -b feature/my-change
# ... commit ...
git push -u origin feature/my-change
# open PR → wait for CI
# merge to main → production deploys automatically
```

Watch: [github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/actions](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/actions)

Health:

```bash
curl -fsS https://relayiq.app/api/health
```

Expected:

```json
{
  "status": "ok",
  "app": "RelayIQ",
  "version": "2026.09.08.abc1234",
  "checked_at": "...",
  "checks": { "app": "ok", "database": "ok" }
}
```

## Workflows

| File | Purpose |
|------|---------|
| `.github/workflows/tests.yml` | Reusable job: Pint, Composer/npm audit, PHPUnit, typecheck, Vite build |
| `.github/workflows/ci.yml` | Runs tests on PRs and feature branches |
| `.github/workflows/deploy-production.yml` | Tests + `/deploy/agent` + `/api/health` + `deploy-*` git tag |
| `.github/workflows/deploy-staging.yml` | Same pattern for `develop` when staging exists |
| `.github/workflows/docs-pages.yml` | Docs → GitHub Pages |

Server-side scripts (already in the repo):

| File | Purpose |
|------|---------|
| `LARAVEL_BACKEND/scripts/post-deploy.sh` | composer, migrate, caches, queue restart, VERSION stamp |
| `LARAVEL_BACKEND/deploy.sh` | Full install + Vite build + migrate (use if Node is on the server) |
| `LARAVEL_BACKEND/scripts/rollback.sh` | Check out previous `deploy-*` tag and re-run post-deploy |

## Rollback

After a successful production deploy, Actions creates a tag like `deploy-20260908-2015-1624a08`.

On the server:

```bash
cd /home/qkbghwib/relayiq.app/LARAVEL_BACKEND
bash scripts/rollback.sh
# or: bash scripts/rollback.sh deploy-YYYYMMDD-HHMM-sha
```

Then confirm `curl -fsS https://relayiq.app/api/health`.

## Staging (later)

Production stays on `main` → https://relayiq.app.

When you add a staging site:

1. Create branch `develop`
2. Point `staging.relayiq.app` at a second cPanel git checkout
3. GitHub variable `STAGING_ENABLED=true`
4. Secrets `STAGING_DEPLOY_AGENT_KEY` and `STAGING_DEPLOY_URL`

Until those exist, the staging workflow's deploy job is skipped.

## Cursor / AI agents

GitHub Actions on `main` is automatic. Cursor agents must still **ask which branch to deploy** before calling `/deploy/agent` directly. See [Agent Deployment Guide](AGENT_DEPLOYMENT_GUIDE.md).

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Deploy job: `DEPLOY_AGENT_KEY is required` | Add the Actions secret; it must match the server `.env` |
| HTTP 401 from `/deploy/agent` | Key mismatch between GitHub and server |
| HTTP 409 | Another deploy is running; Actions retries |
| Health check fails | Server log `storage/logs/laravel.log`; confirm document root is `.../LARAVEL_BACKEND/public` |
| cPanel still on an old commit | Do not click Update in cPanel; inspect Actions logs, then `git -C /home/qkbghwib/relayiq.app log -1` |
| CSS/JS missing | `public/build/manifest.json` missing; build in CI and commit, or run `npm run build` on the server |
| Tests pass locally but CI fails | PHP 8.2 + Node 20; SQLite in-memory matches `phpunit.xml` |

## Related

- [Deployment Guide](deployment.md)
- [Agent Deployment Guide](AGENT_DEPLOYMENT_GUIDE.md)
- [Environment Variables](environment-variables.md)
