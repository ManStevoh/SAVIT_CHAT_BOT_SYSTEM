---
title: Git Reconnect Guide
parent: Home
nav_order: 98
---

# GitHub connection status

**Remote:** [github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM)

## Branches on GitHub

| Branch | Contents | Purpose |
|--------|----------|---------|
| `main` | Frontend at repo root (`app/`, `components/`, …) | **Vercel deploys from here** — do not force-push |
| `docs-github-pages` | New documentation site in `/docs` | **Merge to `main`** to publish docs |
| `monorepo` | Full local project (`FRONTED/`, `LARAVEL_BACKEND/`, `docs/`) | Backup of complete monorepo |
| `backend` | Laravel backend (existing) | Backend-only branch |

## Local setup (`c:\SAVIT_CHAT_BOT`)

| Local branch | Tracks | Use for |
|--------------|--------|---------|
| `main` | `origin/monorepo` | Daily work on full monorepo |
| `docs-github-pages` | `origin/docs-github-pages` | Docs updates matching GitHub frontend layout |

Remote is configured:

```
origin  https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM.git
```

## Publish documentation (one-time)

1. **Merge the docs PR:**  
   [Create / open PR: docs-github-pages → main](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/compare/main...docs-github-pages)

2. **Enable GitHub Pages:**  
   Repo **Settings → Pages → Source:** branch `main`, folder **`/docs`**

3. **Docs URL:**  
   **https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/**

## Push frontend changes to GitHub (Vercel)

GitHub `main` expects frontend at **repo root**, not inside `FRONTED/`.

**Option A — work from synced branch:**

```powershell
cd c:\SAVIT_CHAT_BOT
git fetch origin
git checkout docs-github-pages   # same layout as GitHub main
# copy changed files from FRONTED/ to root, or develop on this branch
git add .
git commit -m "Your message"
git push origin docs-github-pages:main   # push to GitHub main (careful)
```

**Option B — push from FRONTED folder (old workflow):**

```powershell
cd c:\SAVIT_CHAT_BOT\FRONTED
git init
git remote add origin https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM.git
git fetch origin
git checkout -B main origin/main
# make changes, then:
git push origin main
```

## Push monorepo updates

```powershell
cd c:\SAVIT_CHAT_BOT
git checkout main
git add .
git commit -m "Your message"
git push origin main:monorepo
```

## Why two layouts?

| GitHub `main` | Local monorepo |
|---------------|----------------|
| `app/page.tsx` | `FRONTED/app/page.tsx` |
| `docs/` | `docs/` (same after docs merge) |
| — | `LARAVEL_BACKEND/` (on `monorepo` branch only) |

Vercel is wired to GitHub `main` at repo root. The monorepo keeps frontend + backend together locally without breaking deploys.
