# RelayIQ — Autonomous Agent Deployment Guide

> **Audience**: AI Coding Agents, Automated DevOps Scripts, and Engineers.  
> **Purpose**: This guide provides everything an autonomous agent needs to safely build, commit, push, trigger, and verify deployments to the **RelayIQ** production environment without human manual intervention.

---

> [!CAUTION]
> ### 🔴 SHIP THROUGH THE CI/CD PIPELINE
> Pull latest `origin/main`, push, open a PR to `main`, and merge it. GitHub Actions **Deploy to production** runs tests then deploys. If CI fails, fix the failures and push again until green.
>
> Do not use `POST /deploy/agent` as the primary path. Default branch is `main`.

See the canonical copy: [`docs/technical/AGENT_DEPLOYMENT_GUIDE.md`](../docs/technical/AGENT_DEPLOYMENT_GUIDE.md)
