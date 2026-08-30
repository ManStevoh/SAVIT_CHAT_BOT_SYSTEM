# AI AGENT INSTRUCTIONS FOR RELAYIQ

Welcome! Before you perform tasks in this repository, please observe the following core rules:

---

## 🔴 MANDATORY DEPLOYMENT RULE

**BEFORE TRIGGERING ANY SERVER DEPLOYMENT, YOU MUST ALWAYS ASK AND CONFIRM WITH THE DEVELOPER WHICH BRANCH TO USE.**

Under no circumstances should an agent trigger a deployment unilaterally without explicit confirmation of the target branch (e.g. `main`, `staging`, `feature/...`).

### Standard 4-Step Protocol:
1. **Build Client Assets**: If changes touch `LARAVEL_BACKEND/resources/` (React/CSS/TypeScript), run:
   ```bash
   cd LARAVEL_BACKEND && npm run build
   ```
2. **Commit & Push to GitHub**:
   ```bash
   git add -A && git commit -m "..." && git push origin <branch>
   ```
3. **Ask Developer for Confirmation**:
   > *"I have compiled the build assets and pushed to GitHub. Which branch should I deploy to the server? (e.g. `main`)*"
4. **Trigger Deployment Stream (Only after confirmation)**:
   Trigger via `POST https://relayiq.app/deploy/agent` with `X-Deploy-Agent-Key: <DEPLOY_SECRET>` from `LARAVEL_BACKEND/.env`.

---

## Complete Guide
For detailed pipeline architecture, SSE log streaming anatomy, error codes, and troubleshooting, read:
👉 [`docs/technical/AGENT_DEPLOYMENT_GUIDE.md`](docs/technical/AGENT_DEPLOYMENT_GUIDE.md) or [`LARAVEL_BACKEND/AGENT_DEPLOYMENT_GUIDE.md`](LARAVEL_BACKEND/AGENT_DEPLOYMENT_GUIDE.md)
