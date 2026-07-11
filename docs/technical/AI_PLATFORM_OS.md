---
title: AI Platform Operating System
parent: Home
nav_order: 9
description: Verified Layer 3 — world model, background thinking, opportunities, trust, health score, Executive AI APIs.
---

# AI Platform Operating System (Layer 3)

**Status:** Production foundation — verified by `CommerceAgentPlatformTest` and `agent:verify`.

**Master reference:** [Artificial Business Intelligence (ABI) Platform](AI_ABI_PLATFORM.md)

This layer answers: *Does the AI maintain a living model of the business, find opportunities, score health, and leave an auditable trail of decisions?*

---

## Purpose

Treat SAVIT as a **platform** other capabilities build on:

- Continuous **world model** (not amnesiac chat)  
- **Background thinking** when no one is messaging  
- **Opportunity detection** (strategy analyst)  
- **Trust / explainability** logs  
- **Approval queue** for high-risk actions  
- **Organizational memory**  
- **Business health score**  
- **Executive AI** APIs (top decisions)  
- **Skill modules** by industry  

---

## Long-term architecture (foundation shipped)

```text
Channels (WhatsApp today)
    → Conversation Gateway (ProcessIncomingWhatsAppMessage)
    → Chief Executive AI (CommerceAgentOrchestrator)
        → Planning / Reasoning / Memory / Goals / Reflection / Trust
        → Skill modules → Tool bus (14 tools) → Business data
```

---

## World model (#19)

**Class:** `App\Services\Agent\Platform\BusinessWorldModelService`  
**Table:** `business_world_snapshots`

### `build($company)` fields (verified from code)

```text
updated_at
customers.unique_phones
customers.intent_chains_active   (last 14 days)
customers.memories_stored
products.active_count
products.low_stock[]             (id, name, stock) — threshold from config
orders.paid_last_30_days
orders.revenue_last_30_days
orders.pending_payment
goals[]                          (from settings or catalog keys)
risks[]                          (pending payments, low stock)
```

### Methods

| Method | Behavior |
|--------|----------|
| `build` | Live aggregate from DB |
| `snapshot($company, $trigger)` | Persist JSON to `business_world_snapshots` |
| `getForPrompt` | Compact prompt injection for Chief |

**Triggers:** `background_thinking`, `test`, scheduled job, etc.

**Test:** `test_world_model_snapshot_persists`

---

## Continuous background thinking (#20)

**Class:** `App\Services\Agent\Platform\BackgroundThinkingService`  
**Job:** `App\Jobs\Agent\RunBackgroundThinkingJob`

### When it runs

1. **Hourly** for all active companies with `agent_commerce_enabled`  
2. **Delayed post-chat** (`agent.platform.background_thinking_delay_minutes`, default 50)

### What it does (current `handle`)

1. `BackgroundThinkingService::processCompany` → world snapshot + opportunity detection  
2. `BusinessHealthScoreService::computeForCompany`  
3. `ToolProposalService::detectForCompany` (Cognitive)  
4. `KnowledgeCompressionService::compressForCompany` (Cognitive)  

**Note:** Opportunity detection runs inside `processCompany`; the job no longer double-calls the detector (fixed during Platform hardening).

---

## Opportunity detection (#21)

**Class:** `App\Services\Agent\Platform\OpportunityDetectionService`  
**Table:** `business_opportunities`

| Type | Logic |
|------|-------|
| `bundle` | Co-purchased product pairs ≥ 3 times in 90 days (paid orders) |
| `restock` | Active products with stock ≤ `low_stock_threshold` |
| `clear_inventory` | Active products with stock but no paid sales in 60 days |

Each opportunity stores `evidence`, `estimated_impact`, `priority` (`high`/`medium`/`low`), `status` (`open`).

**Test:** `test_opportunity_detection_finds_bundle_pattern` (requires `orders.customer_name`)

**API:** `GET /api/company/executive-ai/opportunities` — if none open, runs detection then returns.

---

## Trust layer (#33)

**Class:** `App\Services\Agent\Platform\AgentTrustService`  
**Table:** `agent_trust_logs`

Every successful Chief reply logs:

- `action_type` (e.g. `customer_reply`)  
- `goal` (chosen plan)  
- `reasoning_summary`  
- `tools_used`  
- `data_consulted` (perception, sentiment, debate roles)  
- `confidence` (**computed** by Cognitive layer — not hard-coded)  
- `outcome`  
- `explainability` (preview, hypotheses, governance, episode_id)  

**Test:** `test_trust_log_created_by_orchestrator`

---

## Human approval workflows (#29)

**Class:** `App\Services\Agent\Platform\AgentApprovalService`  
**Table:** `agent_action_requests`

| Method | Behavior |
|--------|----------|
| `riskLevelForTool` | From `config('agent.platform.tool_risk_levels')` |
| `requiresApproval` | `true` only if risk === `high` |
| `queue` | Creates pending request |

**Wired in:** `AgentToolRunner` — high-risk tools do not execute; return `pending_approval`.

**Honest status:** All 18 tools are currently `low` or `medium` in config → **approval queue is ready but not auto-triggered**. Add a `high` risk tool (e.g. refund) to activate.

**API:** `GET /api/company/executive-ai/approvals` — lists pending requests.  
**Missing:** approve/reject/execute endpoints + dashboard UI.

---

## Organizational memory (#23)

**Class:** `App\Services\Agent\Platform\OrganizationalMemoryService`  
**Table:** `organizational_memories` (`category`, `title`, `content`, `source`)

Injected into Chief prompt. Distinct from **strategic memories** (Cognitive — tactics with success scores).

**Test:** `test_organizational_memory_stored`

---

## Business health score (#30)

**Class:** `App\Services\Agent\Platform\BusinessHealthScoreService`  
**Table:** `business_health_scores` (unique per company + `score_date`)

Composite `overall_score` + `factors` JSON + optional `trends` + `summary`.

Computed during background thinking; shown on Executive dashboard.

**Test:** `test_health_score_computed`

---

## Executive AI (#26 + morning decisions)

**Class:** `App\Services\Agent\Platform\ExecutiveBriefService`

`topDecisionsForCompany($company, $limit = 3)`:

1. Pending unpaid orders from world model  
2. Open opportunities ordered by priority (`CASE` SQL — SQLite-safe; not MySQL `FIELD()`)  
3. Health score below 60 → review drivers  

Each decision includes `evidence`, `risk`, `requires_approval`.

Attached to commerce briefs as `executive_decisions`.

### Executive APIs

| Method | Path | Payload highlights |
|--------|------|--------------------|
| GET | `/api/company/executive-ai/dashboard` | `worldModel`, `healthScore`, `topDecisions`, `pendingApprovals`, `openOpportunities` |
| GET | `/api/company/executive-ai/opportunities` | `opportunities[]` |
| GET | `/api/company/executive-ai/approvals` | `approvals[]` |

**Test:** `test_executive_dashboard_api`

**Controller:** `App\Http\Controllers\Api\Company\ExecutiveAiController`

---

## Skill marketplace foundation (#31)

**Class:** `App\Services\Agent\Platform\SkillModuleRegistry`  
**Config:** `agent.platform.skill_modules`

| Industry key | Module name | Prompt focus |
|--------------|-------------|--------------|
| `retail` | Retail Assistant | Discovery, stock, pricing |
| `restaurant` | Hospitality Assistant | Menu, timing |
| `services` | Services Assistant | Qualify before quote |
| `other` | General Commerce Assistant | Adapt |

Selected from `companies.industry`. Adds `prompt_addon` (+ recommended tools listed in config — **prompt influence today; tool allowlist not yet restricted by module**).

**Test:** `test_skill_module_for_retail_industry`

---

## Pillars #19–#35 status (verified)

| # | Pillar | Status | Notes |
|---|--------|--------|-------|
| 19 | World model | **Implemented** | See above |
| 20 | Background thinking | **Implemented** | Hourly + post-chat |
| 21 | Opportunity detection | **Implemented** | Bundle / restock / slow |
| 22 | Autonomous experiments | **Roadmap** | No A/B framework yet |
| 23 | Organizational memory | **Implemented** | Table + prompt |
| 24 | Multi-step negotiation | **Partial** | Reasoning + goals; no discount approval tool |
| 25 | Business simulation | **Implemented** | Moved to Cognitive `SimulationService` + API (Platform doc previously said Future — corrected) |
| 26 | Explainable decisions | **Implemented** | Trust logs + evidence |
| 27 | Operating manuals | **Partial** | Guides + knowledge artifacts |
| 28 | Team collaboration | **Partial** | APIs only; no role dashboards |
| 29 | Approval workflows | **Foundation** | Model + gate; no high-risk tools / UI |
| 30 | Health score | **Implemented** | Daily composite |
| 31 | Skills marketplace | **Foundation** | Config modules |
| 32 | Workflow builder DSL | **Roadmap** | — |
| 33 | Trust layer | **Implemented** | Every reply logged |
| 34 | Voice-first | **Roadmap** | — |
| 35 | Cross-business learning | **Partial** | `platform_intelligence_patterns` (Cognitive) |

---

## Migrations (Layer 3)

`2026_07_11_150000_create_ai_platform_tables.php`:

- `business_world_snapshots`
- `business_opportunities`
- `agent_trust_logs`
- `agent_action_requests`
- `organizational_memories`
- `business_health_scores`
- `commerce_briefs.executive_decisions`

---

## Verification

```bash
php artisan test --filter=CommerceAgentPlatform
php artisan agent:verify
```

### Manual smoke

1. Enable agent mode  
2. Dispatch `RunBackgroundThinkingJob` for a company  
3. Check `business_world_snapshots`, `business_health_scores`, opportunities  
4. Call Executive dashboard API  
5. Send a chat; confirm `agent_trust_logs` row with non-null confidence  

---

## Code map

| Path | Role |
|------|------|
| `Platform/BusinessWorldModelService.php` | World model |
| `Platform/BackgroundThinkingService.php` | Background cognition |
| `Platform/OpportunityDetectionService.php` | Opportunities |
| `Platform/AgentTrustService.php` | Trust logs |
| `Platform/AgentApprovalService.php` | Approvals |
| `Platform/OrganizationalMemoryService.php` | Org memory |
| `Platform/BusinessHealthScoreService.php` | Health |
| `Platform/ExecutiveBriefService.php` | Top decisions |
| `Platform/SkillModuleRegistry.php` | Skills |
| `Jobs/Agent/RunBackgroundThinkingJob.php` | Scheduler entry |
| `Http/Controllers/Api/Company/ExecutiveAiController.php` | APIs |

---

## Roadmap (Layer 3)

1. Executive dashboard UI  
2. Approve/reject/execute action requests  
3. High-risk tools (refund, price change) with approval  
4. Autonomous A/B experiments (#22)  
5. Workflow builder DSL (#32)  
6. Voice owner commands (#34)  

See also: [AI Agent OS](AI_AGENT_OS.md) · [AI Company OS](AI_COMPANY_OS.md) · [AI Cognitive OS](AI_COGNITIVE_OS.md) · [ABI Platform](AI_ABI_PLATFORM.md)
