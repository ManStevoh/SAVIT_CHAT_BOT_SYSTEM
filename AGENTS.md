# AI AGENT INSTRUCTIONS FOR RELAYIQ

Welcome! Before you perform tasks in this repository, please observe the following core rules:

---

## 🔴 SHIP THROUGH THE CI/CD PIPELINE

Production ships only through GitHub Actions. Merge to `main` triggers **Deploy to production** (tests, then cPanel). Do not use `POST /deploy/agent` as the primary path. If CI fails, fix the failures and push again until the pipeline is green.

### Standard pipeline:
1. **Pull `origin/main`**: `git fetch origin main` and merge/rebase onto it before pushing.
2. **Build Client Assets**: If changes touch `LARAVEL_BACKEND/resources/` (React/CSS/TypeScript), run:
   ```bash
   cd LARAVEL_BACKEND && npm run build
   ```
3. **Commit, Push, and PR to `main`**:
   ```bash
   git add -A && git commit -m "..." && git push origin <branch>
   ```
   Open a PR targeting `main`, then merge it.
4. **Watch GitHub Actions**. If tests or deploy fail, fix and push again. Do not leave red checks.

Default deploy branch is always `main`. Only use a different branch if the developer names one.

---

## 🔍 AGENT LOG INSPECTION & DEBUGGING

When diagnosing bugs, failed webhooks, or AI interactions, agents should query the secure **Agent Log Gateway** instead of requesting large files:

```bash
# Query recent error logs
curl -s -X POST "https://relayiq.app/logs/agent" \
  -H "X-Deploy-Agent-Key: <DEPLOY_SECRET>" \
  -H "Content-Type: application/json" \
  -d '{"channel": "laravel", "level": "error", "lines": 50}'
```

Channels available: `laravel` (default), `whatsapp`, `agent`, `deploy`, `migrate`, `system_db`, `ai_requests`, `all`.

---

## 🛒 AGENT STORE & CATALOG MANAGEMENT

To add, update, archive, or remove items from a live store **without deploying code or writing seeders**, use the **Agent Store Gateway**:

```bash
# Add a product to Store #1:
curl -s -X POST "https://relayiq.app/api/agent/store" \
  -H "X-Deploy-Agent-Key: <DEPLOY_SECRET>" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_product",
    "store": 1,
    "product": {"name": "Cold Brew", "price": 4.50, "stock": 50, "category": "Coffee"}
  }'

# Remove / archive a product from Store #1:
curl -s -X POST "https://relayiq.app/api/agent/store" \
  -H "X-Deploy-Agent-Key: <DEPLOY_SECRET>" \
  -H "Content-Type: application/json" \
  -d '{"action": "remove_product", "store": 1, "name": "Cold Brew"}'
```

---

## Complete Guides
- **Deployment Pipeline**: 👉 [`docs/technical/AGENT_DEPLOYMENT_GUIDE.md`](docs/technical/AGENT_DEPLOYMENT_GUIDE.md) or [`LARAVEL_BACKEND/AGENT_DEPLOYMENT_GUIDE.md`](LARAVEL_BACKEND/AGENT_DEPLOYMENT_GUIDE.md)
- **Log Inspection & Streaming**: 👉 [`docs/technical/AGENT_LOGS_GUIDE.md`](docs/technical/AGENT_LOGS_GUIDE.md) or [`LARAVEL_BACKEND/AGENT_LOGS_GUIDE.md`](LARAVEL_BACKEND/AGENT_LOGS_GUIDE.md)
- **Store & Catalog Gateway**: 👉 [`docs/technical/AGENT_STORE_GUIDE.md`](docs/technical/AGENT_STORE_GUIDE.md) or [`LARAVEL_BACKEND/AGENT_STORE_GUIDE.md`](LARAVEL_BACKEND/AGENT_STORE_GUIDE.md)

