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

1. Open **[Settings → Pages](https://github.com/ManStevoh/SAVIT_CHAT_BOT_SYSTEM/settings/pages)**.
2. **Source:** Deploy from a branch → **Branch:** `main` → **Folder:** `/docs`
3. Click **Save** — build takes 1–3 minutes.

**Live URL:** https://manstevoh.github.io/SAVIT_CHAT_BOT_SYSTEM/

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
