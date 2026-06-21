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

1. Open your repository on GitHub.
2. Go to **Settings → Pages**.
3. Under **Build and deployment → Source**, select **Deploy from a branch**.
4. Under **Branch**, choose `main` (or your default branch) and folder **`/docs`**.
5. Click **Save**.

GitHub will build the site within a few minutes. Refresh the Pages settings page to see the live URL.

## Shareable documentation URL

After Pages is enabled, your docs will be available at:

| Repository type | URL pattern |
|-----------------|-------------|
| **Project site** (most common) | `https://<github-username-or-org>.github.io/<repository-name>/` |
| **User/org site** (repo named `username.github.io`) | `https://<username>.github.io/` |

**Example:** If your repo is `https://github.com/savitglobalsolutions/SAVIT_CHAT_BOT`, the docs URL is:

```
https://savitglobalsolutions.github.io/SAVIT_CHAT_BOT/
```

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
