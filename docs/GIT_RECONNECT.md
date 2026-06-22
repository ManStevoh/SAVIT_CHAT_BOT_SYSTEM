---
title: Git Reconnect Guide
parent: Home
nav_order: 98
---

# Reconnecting to GitHub

Your code lives at **[github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM)**.

## Current layout

| Location | Structure |
|----------|-----------|
| **GitHub** (`feature/inertia-unified` → merge to `main`) | `LARAVEL_BACKEND/` (Laravel + Inertia UI), `docs/` |
| **Local** (`c:\SAVIT_CHAT_BOT`) | Same — single app in `LARAVEL_BACKEND/` |

The old standalone Next.js frontend (`FRONTED/` and root `app/`) has been **removed**. UI is in `LARAVEL_BACKEND/resources/js/`.

## Docs (GitHub Pages)

**Live URL:** **https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/**

Pages deploy via `.github/workflows/docs-pages.yml` (GitHub Actions). Ensure **Settings → Pages → Source: GitHub Actions**.

## Deploy

- **App:** Laravel on cPanel (or any PHP host) — see [Inertia migration guide](../LARAVEL_BACKEND/INERTIA_MIGRATION.md)
- **Vercel:** Disabled (`vercel.json` sets `deploymentEnabled: false`) — no separate frontend host needed

## Remote

```powershell
cd c:\SAVIT_CHAT_BOT
git remote -v
# origin  https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM.git
```

Push the active branch:

```powershell
git push -u origin feature/inertia-unified
```

Merge to `main` on GitHub when ready for production.
