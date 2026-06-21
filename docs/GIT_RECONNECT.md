---
title: Git Reconnect Guide
parent: Home
nav_order: 98
---

# Reconnecting to GitHub

Your code already lives at **[github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM)** (69 commits, Vercel-connected).

## What happened

When documentation was set up locally, git was initialized at the **parent folder** `SAVIT_CHAT_BOT` as a **new** repository. That:

1. Removed the nested `.git` inside `FRONTED/` that was linked to GitHub
2. Created 2 new local commits (monorepo + docs) with **no connection** to GitHub
3. Did **not** delete or change anything on GitHub — your remote repo is unchanged

| Location | Structure | Git history |
|----------|-----------|-------------|
| **GitHub** (`ManStevoh/SAVIT_CHAT_BOT_SYSTEM`) | Frontend at repo root (`app/`, `components/`, …) | 69 commits |
| **Local** (`c:\SAVIT_CHAT_BOT`) | Monorepo (`FRONTED/`, `LARAVEL_BACKEND/`, `docs/`) | 2 new commits, unrelated history |

Vercel deploys from the GitHub repo root — that still works.

## Recommended: push docs only (keeps Vercel working)

Publish the new documentation site without restructuring GitHub:

```powershell
cd c:\SAVIT_CHAT_BOT

# 1. Log in to GitHub (one time)
gh auth login

# 2. Create a branch from your live GitHub main
git fetch origin
git checkout -b docs-github-pages origin/main

# 3. Replace docs/ with the new GitHub Pages site
#    (run from repo root — back up old docs first if needed)
Remove-Item -Recurse -Force docs
Copy-Item -Recurse FRONTED\docs docs-legacy-temp   # optional backup of old frontend docs
# Copy new docs from monorepo commit:
git checkout main -- docs

# 4. Commit and push
git add docs
git commit -m "Add comprehensive GitHub Pages documentation site."
git push -u origin docs-github-pages
```

Then open a PR on GitHub: `docs-github-pages` → `main`, merge, and enable Pages:

**Settings → Pages → Branch: `main`, Folder: `/docs`**

Docs URL: **https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/**

## Alternative: full monorepo on GitHub

Only if you want `FRONTED/` + `LARAVEL_BACKEND/` on GitHub together:

1. In Vercel: set **Root Directory** to `FRONTED`
2. Force-push monorepo (⚠️ rewrites `main` history):

```powershell
git checkout main
git push -u origin main --force
```

Discuss with your team before force-pushing.

## Restore FRONTED-only git link (old workflow)

If you prefer `FRONTED/` to push directly to GitHub again:

```powershell
cd c:\SAVIT_CHAT_BOT\FRONTED
git init
git remote add origin https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM.git
git fetch origin
git checkout -b main origin/main
```

The parent monorepo git at `SAVIT_CHAT_BOT` would then be separate from GitHub.
