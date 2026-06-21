---
title: GitHub Pages Setup
parent: Home
nav_order: 99
---

# Publishing documentation on GitHub Pages

This documentation site is built with [Jekyll](https://jekyllrb.com/) and the [just-the-docs](https://github.com/just-the-docs/just-the-docs) theme. GitHub Pages builds and hosts it automatically from the `/docs` folder.

## Prerequisites

1. Push this repository to GitHub (create a repo if needed).
2. Ensure the `docs/` folder contains `_config.yml` and this documentation.

## Enable GitHub Pages

Your repo is configured for **GitHub Actions** deployment (Settings → Pages).

1. Ensure `.github/workflows/docs-pages.yml` exists on `main` (it deploys from `/docs`).
2. Push to `main` or run the workflow manually: **Actions → Deploy documentation to GitHub Pages → Run workflow**.
3. After a green run, the site is live at:

**https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/**

### Alternative: deploy from branch

If you prefer not to use Actions: **Settings → Pages → Deploy from a branch → `main` → `/docs`**.

### Vercel (frontend app)

Vercel must deploy branch **`main`** only (Next.js at repo root). Do not deploy the `monorepo` branch — set **Root Directory** to `.` or leave blank. `vercel.json` disables auto-deploys from `monorepo`.

Share this link with your team, customers, or partners.

## Optional: fix navigation links

If using a **project site**, update `docs/_config.yml`:

```yaml
url: "https://your-org.github.io"
baseurl: "/SAVIT_CHAT_BOT"
```

Replace `your-org` and `SAVIT_CHAT_BOT` with your GitHub org/username and repository name. Commit and push; GitHub Pages will rebuild automatically.

## Local preview (optional)

```bash
cd docs
gem install bundler jekyll
echo 'gem "just-the-docs"' > Gemfile
bundle install
bundle exec jekyll serve
```

Open `http://localhost:4000` to preview.

## Custom domain (optional)

In **Settings → Pages → Custom domain**, enter e.g. `docs.savitglobalsolutions.com` and configure DNS as GitHub instructs.
