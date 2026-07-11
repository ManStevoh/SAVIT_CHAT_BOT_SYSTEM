---
title: Artificial Business Intelligence (ABI) Platform
parent: Home
nav_order: 6
description: Master verified reference — end-to-end SAVIT AI stack from WhatsApp message to decision intelligence, mapped to ABI Levels 1–20.
---

# Artificial Business Intelligence (ABI) Platform

**Verified against production code on 2026-07-11.**

This document is the **master end-to-end reference** for everything implemented in SAVIT’s AI stack. It is not a vision deck. Every “Implemented” claim below maps to a real PHP class, database table, API route, job, or PHPUnit test that was executed successfully.

| Verification command | Result (2026-07-11) |
|----------------------|---------------------|
| `php artisan agent:verify` | **All schema + 20 tools OK** (38 schema checks) |
| `php artisan test --filter=CommerceAgent` | **72 tests passing** (see layer docs for counts) |
| `php artisan route:list --path=company/cognitive-ai` | 4 routes registered |
| `php artisan route:list --path=company/executive-ai` | 5 routes registered |
| `php artisan route:list --path=company/intelligence` | 1 route registered |
| `php artisan route:list --path=company/commerce-brief` | 1 route registered |

---

## What ABI means (and what it does not)

| Term | Meaning in SAVIT |
|------|------------------|
| **AGI** | Artificial General Intelligence — not the goal |
| **ABI** | **Artificial Business Intelligence** — AI whose domain is understanding, operating, and improving businesses |
| **Digital Nervous System** | Product thesis — every business signal (chat, order, campaign, stock) feeds continuous AI cognition; see [SAVIT Digital Nervous System](SAVIT_DIGITAL_NERVOUS_SYSTEM.md) |
| **Business Consciousness Layer** | Continuous background sensing + prioritization so owners see prepared insights, not empty dashboards |
| **Decision intelligence** | Reasoning as a first-class product: plans, confidence, assumptions, evidence — not only chat replies |

SAVIT is building toward ABI in **four shipped layers** plus a **roadmap of ABI Levels 1–20**:

```text
Layer 1  AI Agent Commerce OS     → tools, memory, WhatsApp loop
Layer 2  AI Company OS            → reasoning, twin, intent, briefs
Layer 3  AI Platform OS           → world model, trust, opportunities, Executive AI
Layer 4  AI Cognitive Architecture→ perception, debate, confidence, critique, DNA
────────────────────────────────────────────────────────────────
Roadmap  ABI Levels 1–20          → ontology, curiosity, Bayesian, board, Intelligence API
```

Layer docs (detail):

- [SAVIT — Digital Nervous System](SAVIT_DIGITAL_NERVOUS_SYSTEM.md) — **strategic master** (concepts 1–24 + consciousness)
- [AI Agent Commerce OS](AI_AGENT_OS.md)
- [AI Company Operating System](AI_COMPANY_OS.md)
- [AI Platform Operating System](AI_PLATFORM_OS.md)
- [AI Cognitive Architecture](AI_COGNITIVE_OS.md)

---

## Part A — End-to-end runtime (what actually happens)

### A.1 Enablement gate

1. Company owner enables **Agent commerce mode** via `PUT /api/company/settings` with `agentCommerceEnabled: true`.
2. Persisted as `company_settings.agent_commerce_enabled`.
3. `CommerceAgentReplyService::isEnabledForCompany($company)` returns true only when that flag is on.

**Verified by:** `CommerceAgentTest::test_agent_mode_disabled_by_default`, `test_agent_mode_enabled_when_company_setting_on`, `CommerceAgentFlowTest::test_settings_api_returns_and_saves_agent_fields`.

### A.2 WhatsApp inbound → Chief Executive AI

**File:** `app/Jobs/ProcessIncomingWhatsAppMessage.php` (approx. lines 134–150)

```text
WhatsApp webhook
    → ProcessIncomingWhatsAppMessage
        → if agent_commerce_enabled:
              CommerceAgentReplyService::generate()
                  → CommerceAgentOrchestrator::run()
              sendReplyAndSave(..., reply_source = agent_cognitive*)
              schedulePostConversationJobs()
        → else: legacy greeting / order flow / FAQ path
```

On success, `messages.reply_source` is one of:

| Route | When |
|-------|------|
| `agent_cognitive` | Normal LLM reply after tool loop |
| `agent_cognitive_order` | Order-flow tool produced a checkout reply |
| `agent_cognitive_handoff` | Escalate / transfer_to_human / pending approval |
| `agent_cognitive_failed` | No usable reply (job falls back to legacy if empty) |

**Verified by:** `CommerceAgentFlowTest::test_whatsapp_job_uses_agent_path_and_sends_reply`, `test_whatsapp_job_falls_back_to_legacy_when_agent_returns_empty`.

### A.3 Cognitive pipeline (before tools)

**File:** `app/Services/Agent/Cognitive/CognitivePipelineService.php` → `processTurn()`

| Step | Service | What it does | Persists to |
|------|---------|--------------|-------------|
| 1 | `PerceptionService` | Rule-based: emotion, topic, urgency, risk, relationship | (in episode) |
| 2 | `ReasoningEngineService` | LLM JSON: understanding, hypotheses, options, plan, specialist_council | `agent_reasoning_traces`, updates `chats.detected_sentiment` |
| 3 | `InternalDebateService` | Sales / Support / Finance / Customer Success / Chief | (in episode) |
| 4 | `ConfidenceScoringService` | Float 0.1–0.99 → `auto_respond` / `clarify` / `escalate` | (in episode) |
| 5 | Prompt assembly | DNA + perception + debate + economic + meta patterns + confidence + reasoning | system prompt |
| 6 | `GovernanceService` | Policy metadata for trust/audit | (in episode) |
| 7 | `CognitiveEpisode::create` | Full turn record | `cognitive_episodes` |

**Verified by:** `CommerceAgentCognitiveTest::test_perception_extracts_disappointed_wrong_product`, `test_cognitive_pipeline_persists_episode`.

#### Perception example (verified)

Input: `"I wanted the black one 😔"`

Output shape (from `PerceptionService::perceive`):

```json
{
  "emotion": "disappointed",
  "topic": "wrong product",
  "urgency": "low",
  "risk": "possible return",
  "relationship": "new customer | existing customer | loyal customer",
  "sentiment": { "label": "...", "score": 0.0, "cues": [] },
  "raw_cues": []
}
```

#### Confidence thresholds (config)

| Config key | Default | Action |
|------------|---------|--------|
| `agent.cognitive.confidence_auto_respond` | `0.70` (`AGENT_CONFIDENCE_AUTO`) | Respond directly |
| `agent.cognitive.confidence_clarify` | `0.45` (`AGENT_CONFIDENCE_CLARIFY`) | Ask clarifying question |
| below clarify | — | Prefer escalate / handoff |

If `confidence_action === escalate`, orchestrator sets `$handoff = true` before the tool loop.

### A.4 Tool loop (action system)

**File:** `app/Services/Agent/CommerceAgentOrchestrator.php`

1. Build system prompt from: base company prompt, skill module, digital twin, world model, org memory, **strategic memory**, business goals, operating guides, intent chain, customer memory, agent reflections, **cognitive prompt block**.
2. Load last N messages (`agent.conversation_history_limit`, default 16).
3. Loop up to `agent.max_loop_iterations` (default 8):
   - `AgentChatService::completeWithTools()` with OpenAI tool definitions
   - For each tool call (cap `agent.max_tool_calls_per_turn`, default 12):
     - `AgentToolRunner::run()` → if risk `high`, queue approval and return `pending_approval` (no execute)
     - Else execute via `AgentToolRegistry` and audit to `agent_tool_invocations`
4. On final text: `SelfCritiqueService::review()` → `ReplyGuardService::guard()` → finalize episode → `AgentTrustService::logDecision()` with **real confidence**.

**Verified by:** `CommerceAgentFlowTest::test_agent_tool_loop_records_multiple_invocations`, `CommerceAgentCognitiveTest::test_orchestrator_uses_cognitive_route_and_trust_confidence`, `CommerceAgentPlatformTest::test_trust_log_created_by_orchestrator`.

### A.5 Eighteen registered tools (allowlist)

Registered in `AppServiceProvider` — `agent:verify` expects **exactly 18**.

| # | Tool name | Risk (config) | Purpose |
|---|-----------|---------------|---------|
| 1 | `search_products` | low | Catalog search (vector + lexical) |
| 2 | `search_faq` | low | FAQ/policy |
| 3 | `search_knowledge` | low | Knowledge chunks |
| 4 | `get_customer_profile` | low | Memory + recent orders |
| 5 | `search_orders` | low | Order by number |
| 6 | `get_catalog` | low | Numbered catalog |
| 7 | `process_order_message` | medium | Checkout state machine |
| 8 | `transfer_to_human` | medium | Handoff |
| 9 | `remember_customer` | low | Persist customer fact |
| 10 | `get_business_info` | low | Hours, payments, timezone |
| 11 | `trace_customer_graph` | low | Customer → orders → products |
| 12 | `get_product_relationships` | low | Product graph: accessories, warranties, bundles |
| 13 | `check_delivery_status` | low | Order shipping / delay status |
| 14 | `get_weather` | low | Open-Meteo weather for delivery/events |
| 15 | `check_mpesa_payment` | low | M-Pesa order payment status |
| 16 | `get_shipping_quote` | low | Shipping cost + ETA (API or heuristic) |
| 17 | `check_calendar_availability` | low | Business hours / appointment slots |
| 18 | `get_marketing_performance` | low | Growth metrics bridge (leads, ROI, posts) |

**Important production fact:** `AgentApprovalService::requiresApproval()` is true only for risk `high`. Current config assigns only `low`/`medium`, so the approval **queue path is wired but not auto-triggered by any tool today**. High-risk tools can be added later without changing the runner.

### A.6 Post-conversation jobs (delayed)

Dispatched from `ProcessIncomingWhatsAppMessage::schedulePostConversationJobs()`:

| Job | Delay config | Work |
|-----|--------------|------|
| `ExtractCustomerMemoriesJob` | `agent.proactive.memory_extraction_delay_minutes` (30) | LLM extract → `customer_memories` (if `learn_from_conversations`) |
| `ReflectOnConversationJob` | `agent.proactive.reflection_delay_minutes` (45) | Reflection → `agent_operating_guides` |
| `RunBackgroundThinkingJob` | `agent.platform.background_thinking_delay_minutes` (50) | World snapshot, opportunities, health, tool proposals, knowledge compression |

### A.7 Scheduled jobs (`bootstrap/app.php`)

| Schedule | Job | Work |
|----------|-----|------|
| Hourly | `ProcessAgentProactiveEventsJob` | Abandoned-cart outreach; reorder signals; **commerce event detection** (low stock, sales drop, delivery delay, birthdays) + customer outreach |
| Daily 07:00 | `GenerateDailyCommerceBriefJob` | Morning brief + executive decisions |
| Hourly | `RunBackgroundThinkingJob` | Background cognition for all agent-enabled companies |

### A.8 Payment proactive path

`OrderPaymentService` calls `AgentProactiveMessageService::paymentReceivedMessage()` when `agent_proactive_enabled` is on.

**Verified by:** `CommerceAgentFlowTest::test_payment_confirmation_uses_proactive_message_when_enabled`.

---

## Part B — Layer inventory (verified)

### B.1 Layer 1 — Agent Commerce OS

| Capability | Implementation | Status |
|------------|----------------|--------|
| Tool server | `AgentToolRegistry` + 18 tools | Implemented |
| Chief loop | `CommerceAgentOrchestrator` | Implemented |
| Customer memory | `CustomerMemoryService` + table | Implemented |
| Agent reflections | `AgentMemoryService` + table | Implemented |
| Business goals | `BusinessGoalService` + settings | Implemented |
| Proactive outreach | `ProcessAgentProactiveEventsJob` | Implemented |
| Feature flag | `agent_commerce_enabled` | Implemented |
| Settings API | GET/PUT agent fields | Implemented |

### B.2 Layer 2 — Company OS

| Capability | Implementation | Status |
|------------|----------------|--------|
| Reasoning engine | `ReasoningEngineService` → `agent_reasoning_traces` | Implemented |
| Sentiment | `MessageSentimentService` (rule-based) | Implemented |
| Intent chains | `CustomerIntentChainService` | Implemented |
| Digital twin | `CompanyDigitalTwinService` + `digital_twin` JSON | Implemented (not in Settings UI API) |
| Operating guides | `AgentOperatingGuideService` + reflection job | Implemented |
| Full LLM reflection | `ConversationReflectionService` — satisfaction_score, improvement_notes | Implemented |
| Knowledge graph | `ProductGraphService` + `product_relationships` + `get_product_relationships` tool | Implemented |
| Vector search | `KnowledgeChunkService` + `VectorSimilarity` (JSON embeddings, in-app cosine) | Implemented |
| Morning brief | `CommerceMorningBriefService` + API | Implemented |
| Council flag | `agent_council_enabled` column | **Column only — not wired in services** |

### B.3 Layer 3 — Platform OS

| Capability | Implementation | Status |
|------------|----------------|--------|
| World model | `BusinessWorldModelService` → snapshots | Implemented |
| Background thinking | `BackgroundThinkingService` + job | Implemented |
| Opportunities | `OpportunityDetectionService` (bundle, restock, slow mover) | Implemented |
| Trust logs | `AgentTrustService` | Implemented |
| Approvals model | `agent_action_requests` + `AgentApprovalService` | Foundation (no high-risk tools yet) |
| Org memory | `OrganizationalMemoryService` | Implemented |
| Health score | `BusinessHealthScoreService` | Implemented |
| Executive decisions | `ExecutiveBriefService` | Implemented |
| Skill modules | `SkillModuleRegistry` (retail/restaurant/services/other) | Foundation |
| Executive APIs | dashboard / opportunities / approvals | Implemented |

### B.5 Phase 3 — Multi-agent specialists & event bus (2026-07-11)

| Capability | Implementation | Status |
|------------|----------------|--------|
| Specialist agents | `SalesSpecialistService`, `SupportSpecialistService`, `InventorySpecialistService` | Implemented |
| Specialist orchestrator | `CommerceSpecialistOrchestrator` — `consultForTurn()` + `dispatchBackgroundPipeline()` | Implemented |
| Specialist runs | `commerce_agent_runs` table + `RunCommerceSpecialistJob` | Implemented |
| Specialist API | `GET/POST /api/company/commerce-specialists/*` | Implemented |
| Event bus | `CommerceEventDetector` + `CommerceEventHandler` + `commerce_agent_events` | Implemented |
| Event types | `low_stock`, `sales_drop`, `delivery_delay`, `customer_birthday` | Implemented |
| Product graph | `product_relationships` (accessory, warranty, bundle, complement, replacement) | Implemented |
| World tools | `check_delivery_status`, `get_weather` (Open-Meteo, no API key) | Implemented |
| Debate integration | `InternalDebateService` injects specialist consults per turn | Implemented |

**Config:** `config/agent.php` → `specialists`, `events`, `world` sections.

**Tests:** `CommerceAgentPhase3Test` (12 tests).

**Verified by:** `php artisan test --filter=CommerceAgentPhase3`; `agent:verify` checks `commerce_agent_runs`, `product_relationships`, `commerce_agent_events` tables.

### B.6 Phase 4 — Vision, owner analytics, unified brain, external tools (2026-07-11)

| Capability | Implementation | Status |
|------------|----------------|--------|
| Vision pipeline | `VisionPipelineService` + multimodal `AgentChatService::completeWithVision()` | Implemented |
| WhatsApp images | `incomingMessageId` on job; vision enriches Chief prompt | Implemented |
| Unified brain | `UnifiedCompanyBrainService` → `company_brain_snapshots` | Implemented |
| Growth ↔ Commerce | Brain merges world model + `GrowthAnalyticsService` | Implemented |
| Owner analytics | `OwnerAnalyticsAgentService` → `owner_analytics_investigations` | Implemented |
| External tools | `check_mpesa_payment`, `get_shipping_quote`, `check_calendar_availability`, `get_marketing_performance` | Implemented |
| Owner alerts | `handleOwnerAlerts()` → `company_notifications` | Implemented |

**APIs:** `GET/POST /api/company/company-brain`, `GET/POST /api/company/owner-analytics/*`

**Tests:** `CommerceAgentPhase4Test` (11 tests). **Tool count: 18.**

**Doc:** [AI Phase 4 OS](AI_PHASE4_OS.md)

World model fields built today (`BusinessWorldModelService::build`):

```text
customers.unique_phones, intent_chains_active, memories_stored
products.active_count, low_stock[]
orders.paid_last_30_days, revenue_last_30_days, pending_payment
goals[]
risks[]
```

### B.4 Layer 4 — Cognitive Architecture

| Capability | Implementation | Status |
|------------|----------------|--------|
| Pipeline | `CognitivePipelineService` | Implemented |
| Perception | `PerceptionService` (rule-based) | Implemented |
| Internal debate | `InternalDebateService` | Implemented |
| Confidence | `ConfidenceScoringService` | Implemented |
| Self-critique | `SelfCritiqueService` (rule-based) | Implemented |
| Business DNA | `BusinessDnaService` + `business_dna` | **Implemented** — Settings → AI → Business DNA presets |
| Strategic memory | `StrategicMemoryService` | Implemented |
| Meta learning | `MetaLearningService` + `platform_intelligence_patterns` | Implemented (seeded patterns) |
| Tool proposals | `ToolProposalService` | Implemented |
| Knowledge compression | `KnowledgeCompressionService` | Implemented |
| Digital workforce | `DigitalWorkforceRegistry` (7 directors) | Implemented (metadata + dashboard) |
| Executive planning | `ExecutivePlanningService` | Implemented |
| Economic reasoning | `EconomicReasoningService` | Implemented |
| Forecast | `ForecastService` | Implemented |
| Causal reasoning | `CausalReasoningService` | Implemented |
| Simulation | `SimulationService` + API | Implemented |
| Governance | `GovernanceService` | Implemented |

---

## Part C — Database schema (agent-related)

### C.1 Migrations (agent core)

| Migration | Creates / alters |
|-----------|------------------|
| `2026_07_11_120000_create_agent_os_tables` | `agent_commerce_enabled`, `customer_memories`, `agent_tool_invocations`, `agent_reflections` |
| `2026_07_11_130000_add_agent_goals_and_proactive` | `agent_business_goals`, `agent_proactive_enabled` |
| `2026_07_11_130001_add_agent_proactive_follow_up_to_orders` | `orders.agent_proactive_follow_up_at` |
| `2026_07_11_140000_create_ai_company_tables` | `digital_twin`, `agent_council_enabled`, `detected_sentiment`, reasoning/intent/guides/briefs |
| `2026_07_11_150000_create_ai_platform_tables` | world snapshots, opportunities, trust logs, action requests, org memory, health scores, `executive_decisions` |
| `2026_07_11_160000_create_ai_cognitive_tables` | `business_dna`, episodes, strategic memories, tool proposals, platform patterns, plans, artifacts, simulations + seed patterns |

### C.2 Tables checklist (`agent:verify`)

All of the following must exist after migrate (verified OK):

`customer_memories`, `agent_tool_invocations`, `agent_reflections`, `agent_reasoning_traces`, `customer_intent_chains`, `agent_operating_guides`, `commerce_briefs`, `business_world_snapshots`, `business_opportunities`, `agent_trust_logs`, `agent_action_requests`, `organizational_memories`, `business_health_scores`, `cognitive_episodes`, `strategic_memories`, `tool_proposals`, `platform_intelligence_patterns`, `executive_plans`, `knowledge_artifacts`, `cognitive_simulations`

Plus columns: `agent_commerce_enabled`, `agent_business_goals`, `agent_proactive_enabled`, `digital_twin`, `business_dna`, `detected_sentiment`, `agent_proactive_follow_up_at`.

---

## Part D — HTTP APIs (authenticated company)

Middleware: `auth:sanctum`, `user.active`, `subscription.active`.

### D.1 Settings

| Method | Path | Agent fields |
|--------|------|--------------|
| GET | `/api/company/settings` | `agentCommerceEnabled`, `agentProactiveEnabled`, `agentBusinessGoals`, `agentBusinessGoalCatalog` |
| PUT | `/api/company/settings` | same writable fields |

**Not exposed via Settings API (set in DB / future UI):** `digital_twin`, `agent_council_enabled`.

### D.2 Commerce brief

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/company/commerce-brief` | Today’s brief + metrics + recommendations + `executiveDecisions` |

### D.3 Executive AI

| Method | Path | Returns |
|--------|------|---------|
| GET | `/api/company/executive-ai/dashboard` | `worldModel`, `healthScore`, `topDecisions`, `pendingApprovals`, `openOpportunities` |
| GET | `/api/company/executive-ai/opportunities` | Open opportunities (auto-detect if empty) |
| GET | `/api/company/executive-ai/approvals` | Pending `agent_action_requests` |

### D.4 Cognitive AI

| Method | Path | Body / returns |
|--------|------|----------------|
| GET | `/api/company/cognitive-ai/dashboard` | architecture, workforce, forecast, causalAnalysis, recentEpisode, counts |
| GET | `/api/company/cognitive-ai/strategic-memories` | `{ memories: [...] }` |
| POST | `/api/company/cognitive-ai/plans` | `{ "goal": "..." }` → plan + breakdown |
| POST | `/api/company/cognitive-ai/simulate` | `{ "scenario_type": "discount", "inputs": {...} }` → scenarios + recommendation |

---

## Part E — Configuration reference

**File:** `config/agent.php`

| Area | Keys | Env overrides |
|------|------|---------------|
| Loop limits | `max_loop_iterations`, `max_tool_calls_per_turn`, `tool_result_max_chars`, `conversation_history_limit` | `AGENT_MAX_*`, `AGENT_HISTORY_LIMIT` |
| Defaults | `default_agent_commerce_enabled` | `AGENT_COMMERCE_DEFAULT` |
| Memory | `customer_memory_limit`, `agent_reflection_limit` | — |
| Goals | `business_goals.*` (5 catalog entries) | — |
| Proactive | `proactive.*` | `AGENT_ABANDONED_CART_HOURS`, `AGENT_MAX_PROACTIVE_OUTREACH`, delay envs |
| Company | `company.reasoning_enabled`, stock thresholds | `AGENT_REASONING_ENABLED`, `AGENT_LOW_STOCK_THRESHOLD` |
| Platform | `platform.tool_risk_levels`, `skill_modules`, bg delay | `AGENT_BG_THINKING_DELAY` |
| Cognitive | confidence thresholds, `business_dna_defaults`, `workforce`, `platform_patterns_seed` | `AGENT_CONFIDENCE_AUTO`, `AGENT_CONFIDENCE_CLARIFY` |

**Code gap:** `SelfCritiqueService` reads `config('agent.max_output_chars', 1200)` but that key is **not** defined in `config/agent.php` (inline default 1200 applies).

---

## Part F — Test matrix (executable proof)

Run:

```bash
cd LARAVEL_BACKEND
php artisan migrate --force
php artisan agent:verify
php artisan test --filter=CommerceAgent
php artisan test --filter=WhatsApp
```

| File | What it proves |
|------|----------------|
| `CommerceAgentTest` | Registry (11), flags, memory, goals prompt, orchestrator reply → `agent_cognitive` |
| `CommerceAgentFlowTest` | WhatsApp E2E, settings API, tools audit, proactive, payment, extraction, multi-tool loop |
| `CommerceAgentCompanyTest` | Sentiment, graph tool, reasoning trace, brief, reflection, brief API |
| `CommerceAgentPlatformTest` | World snapshot, bundles, health, org memory, skills, executive dashboard, trust log |
| `CommerceAgentCognitiveTest` | Perception, episode, critique, DNA, plans, simulation, patterns, compression, cognitive dashboard, orchestrator confidence, tool proposals |

---

## Part G — ABI Levels 1–20 (vision ↔ verified code)

This section maps the long-term **Artificial Business Intelligence** levels to **what ships today**. Status meanings:

- **Implemented** — code + table/API/test exist and were verified
- **Partial** — real foundation; missing depth, UI, or LLM enrichment
- **Roadmap** — designed; not built

### Level 1 — Understanding (business ontology)

**Vision:** “Sales are down” expands into Revenue, Conversion, Traffic, Pricing, Competition, Marketing, Satisfaction, Inventory, Seasonality, Cash Flow.

| Today | Evidence |
|-------|----------|
| **Partial** | World model covers customers, products, orders, goals, risks. Causal service compares 14-day revenue and hypothesizes stock/marketing/seasonality/price. Health score composites multiple factors. |
| Missing | Full formal ontology graph; traffic/competition/cash-flow nodes; automatic expansion of free-text “sales are down” into a full investigation tree via API. |

**Code:** `BusinessWorldModelService`, `CausalReasoningService`, `BusinessHealthScoreService`.

### Level 2 — Curiosity (investigate without being asked)

**Vision:** Owner says sales dropped → AI lists causes and starts collecting evidence across channels.

| Today | Evidence |
|-------|----------|
| **Partial** | Background thinking + opportunity detection run hourly and post-chat without a user prompt. Causal analysis available on cognitive dashboard. |
| Missing | Autonomous multi-source investigation agent that opens website/marketing/competitor probes and writes an investigation case file. |

**Code:** `BackgroundThinkingService`, `RunBackgroundThinkingJob`, `OpportunityDetectionService`, `CausalReasoningService`.

### Level 3 — Hypothesis generation

**Vision:** Multiple hypotheses with confidence percentages, then investigate each.

| Today | Evidence |
|-------|----------|
| **Partial** | `ReasoningEngineService` returns `hypotheses[]` and `options[]` in LLM JSON; causal service returns `likely_causes` with likelihood labels. |
| Missing | Persistent hypothesis objects with Bayesian updates over time; dedicated investigation jobs per hypothesis. |

**Code:** `ReasoningEngineService`, `CausalReasoningService`, `agent_reasoning_traces`.

### Level 4 — Bayesian thinking

**Vision:** Probabilities for buy / churn / refund / delay — uncertainty as first-class.

| Today | Evidence |
|-------|----------|
| **Partial** | Turn confidence score (0.1–0.99) with action thresholds; trust logs store confidence; forecast returns trend confidence `low`/`medium`. |
| Missing | Calibrated probability models for purchase/churn/refund; proper Bayesian update from outcomes. |

**Code:** `ConfidenceScoringService`, `AgentTrustService`, `ForecastService`.

### Level 5 — Counterfactual reasoning

**Vision:** “What if price stayed the same?” → profit, demand, inventory, cash flow.

| Today | Evidence |
|-------|----------|
| **Partial** | `SimulationService` compares discount vs bundle vs no-discount scenarios with estimated revenue/profit/repeat/inventory days; API `POST /cognitive-ai/simulate`. |
| Missing | Full counterfactual engine tied to live inventory/cash models; Monte Carlo; owner UI. |

**Code:** `SimulationService`, `cognitive_simulations`, `CognitiveAiController::simulate`.

### Level 6 — Long-term planning

**Vision:** Multi-year branch expansion plans with hiring, capital, KPIs.

| Today | Evidence |
|-------|----------|
| **Partial** | `ExecutivePlanningService` breaks “double revenue” into director work streams + KPI targets → `executive_plans`. |
| Missing | Multi-year timelines, capital schedules, hiring plans, branch-level models. |

**Code:** `ExecutivePlanningService`, `POST /cognitive-ai/plans`.

### Level 7 — Organizational intelligence

**Vision:** Org chart, who approves what, departments, responsibilities.

| Today | Evidence |
|-------|----------|
| **Partial** | Digital workforce registry (7 directors); approval requests model; governance metadata. |
| Missing | Real org chart, role-based approval routing, employee/project models. |

**Code:** `DigitalWorkforceRegistry`, `AgentApprovalService`, `GovernanceService`.

### Level 8 — Trust modeling

**Vision:** Supplier/courier/employee reliability scores.

| Today | Evidence |
|-------|----------|
| **Partial** | Decision trust logs (`agent_trust_logs`); tool success audits; health score. |
| Missing | Entity trust scores for suppliers/couriers/partners. |

**Code:** `AgentTrustService`, `agent_tool_invocations`.

### Level 9 — Decision economics

**Vision:** Benefit KSh X, cost Y, risk, proceed/hold.

| Today | Evidence |
|-------|----------|
| **Partial** | Economic reasoning (margin, LTV, stock notes); opportunity `estimated_impact`; simulation trade-offs. |
| Missing | Explicit benefit/cost/risk decision objects with currency amounts and proceed recommendations as a product API. |

**Code:** `EconomicReasoningService`, `OpportunityDetectionService`, `SimulationService`.

### Level 10 — Resource allocation

**Vision:** Trade-offs across marketing vs inventory vs hiring vs automation.

| Today | Evidence |
|-------|----------|
| **Roadmap** | Executive plans assign work to directors; no capital allocation optimizer. |

### Level 11 — Organizational memory (reasons, not only data)

**Vision:** “Why did we raise prices in 2027?” → meeting notes, impact, feedback.

| Today | Evidence |
|-------|----------|
| **Partial** | `organizational_memories`, `strategic_memories` (tactics + outcomes), operating guides, knowledge artifacts. |
| Missing | Decision provenance linking meetings, price changes, and measured outcomes over years. |

**Code:** `OrganizationalMemoryService`, `StrategicMemoryService`, `KnowledgeCompressionService`.

### Level 12 — Multi-business intelligence

**Vision:** One AI across supermarket + pharmacy + school + logistics with permissions.

| Today | Evidence |
|-------|----------|
| **Roadmap** | Multi-tenant company isolation exists; no multi-company group / ecosystem reasoning. |

### Level 13 — Autonomous departments

**Vision:** Finance/Sales/Ops departments with specialist AIs.

| Today | Evidence |
|-------|----------|
| **Implemented (foundation)** | Three specialist services (Sales/Support/Inventory) with per-turn consult + background LLM/rule analysis; `commerce_agent_runs` audit; wired into `InternalDebateService`. Chief still executes tools — specialists advise. |
| Missing | Fully autonomous department queues with separate tool allowlists and daily owner reports. |

**Code:** `CommerceSpecialistOrchestrator`, `SalesSpecialistService`, `SupportSpecialistService`, `InventorySpecialistService`, `InternalDebateService`.

### Level 14 — Digital board of directors

**Vision:** Virtual board meeting for “open branch in Kisumu?”

| Today | Evidence |
|-------|----------|
| **Partial** | Debate + executive brief top decisions; simulation for scenarios. |
| Missing | Formal board session object, multi-round debate, recorded vote, Kisumu-style geo expansion model. |

### Level 15 — Adaptive personalities

**Vision:** Support empathetic, Sales persuasive, Finance analytical — brand consistent.

| Today | Evidence |
|-------|----------|
| **Partial** | Business DNA (tone, values, risk, escalation); skill modules; `ai_tone` on settings. |
| Missing | Per-role personality profiles with enforced style transfer per specialist. |

**Code:** `BusinessDnaService`, `SkillModuleRegistry`.

### Level 16 — Knowledge evolution

**Vision:** Daily: what learned / changed / obsolete / update.

| Today | Evidence |
|-------|----------|
| **Implemented** | `ConversationReflectionService` runs full LLM post-conversation review → `satisfaction_score`, `improvement_notes`, operating guides; `agent_reflections.metadata` stores full JSON. |
| Missing | Explicit daily knowledge evolution job with obsolescence detection. |

### Level 17 — Ethical guardrails

**Vision:** Policies, legal, privacy, approval thresholds; explain refusals.

| Today | Evidence |
|-------|----------|
| **Partial** | Governance context; reply guard; high-risk approval gate (wired); confidence escalate; trust audit. |
| Missing | Policy engine DSL, legal packs, refusal explanations as customer-visible structured events. |

**Code:** `GovernanceService`, `ReplyGuardService`, `AgentApprovalService`.

### Level 18 — Digital business genome

**Vision:** Vision, mission, culture, brand, pricing, strengths, weaknesses, risk appetite as identity model.

| Today | Evidence |
|-------|----------|
| **Partial** | `business_dna` + industry defaults; `digital_twin` JSON; goals. |
| Missing | Full genome schema, versioning, and genome-driven planning constraints. |

**Code:** `BusinessDnaService`, `CompanyDigitalTwinService`.

### Level 19 — Intelligence API

**Vision:** `POST /reason` with goal, constraints, time, context → plan, confidence, assumptions, actions.

| Today | Evidence |
|-------|----------|
| **Implemented** | `POST /api/company/intelligence/reason` — `IntelligenceReasoningService` orchestrates world model, owner investigation, causal analysis, health, forecast, executive decisions, auto simulation/plan, hypotheses, assumptions, recommended actions. UI: **Cognitive AI** dashboard “Ask the business”. |
| Also | `POST /cognitive-ai/plans`, `POST /cognitive-ai/simulate`, executive dashboard APIs. |

**Code:** `IntelligenceReasoningService`, `IntelligenceController`, `CommerceAgentIntelligenceTest`.

### Level 20 — The intelligence loop

**Vision:** Observe → Understand → Remember → Reason → Predict → Plan → Simulate → Decide → Execute → Measure → Reflect → Learn → Improve → Repeat — never stops.

| Today | Evidence |
|-------|----------|
| **Partial — loop exists in pieces** | Perceive/reason/debate (understand), memories (remember), forecast (predict), plans (plan), simulate (simulate), tools (execute), trust/health (measure), reflection/compression (learn), background thinking (repeat). |
| Missing | Single orchestrated loop controller with measurable KPIs per stage and continuous improvement metrics. |

---

## Part H — Decision intelligence (the product thesis)

Most AI products expose chat, search, or automation.

SAVIT’s differentiating product surface should be **business reasoning**:

> “Should I hire another salesperson, spend KSh 300,000 on Facebook ads, or open a new branch?”

**What exists today to support that answer:**

1. Collect data — world model, orders, stock, health score, opportunities  
2. Identify gaps — reasoning `missing_info`, confidence clarify/escalate  
3. Scenarios — `SimulationService` + `POST /cognitive-ai/simulate` + auto in `POST /intelligence/reason`  
4. Trade-offs — economic notes + simulation estimates  
5. Explain — trust logs + opportunity evidence + executive decisions  
6. Recommend — **`POST /intelligence/reason`** + executive brief top 3 + plan breakdown  

**What is still required for a true Decision Intelligence product:**

1. Persistent investigation **cases** with multi-step probes (Level 2–3)  
2. Calibrated probabilities (Level 4)  
3. Outcome measurement — did the recommendation work? (Level 20 loop closure)  
4. Org chart approval routing (Level 7)  
5. Multi-business ecosystem reasoning (Level 12)  

---

## Part I — Production enablement checklist

1. Deploy code; run `php artisan migrate --force`
2. Run `php artisan agent:verify` — all OK
3. Ensure queue workers + scheduler: `php artisan schedule:run` (cron)
4. Dashboard → **Settings → AI** → **Auto-reply ON** + **Agent commerce mode ON**
5. Optional: **Business DNA** preset (Luxury / Café / Custom)
6. Optional: **Proactive mode ON**; select business goals
7. Open **Cognitive AI** → test `POST /intelligence/reason` via UI
8. Open **Executive AI** → brief, approvals, opportunities
9. Send WhatsApp test message; confirm `messages.reply_source` starts with `agent_cognitive`
10. Inspect `cognitive_episodes`, `agent_trust_logs`, `owner_analytics_investigations`

---

## Part J — Honest limitations (do not oversell)

| Claim to avoid | Reality |
|----------------|---------|
| “Full AGI / ABI complete” | Layers 1–4 are production foundations; ABI Levels 1–20 are mostly Partial/Roadmap |
| “LLM perception” | Perception + sentiment + critique are **rule-based** today; reasoning uses LLM |
| “Approval workflows live for all tools” | Gate exists; `send_whatsapp_campaign` and `issue_order_refund` are high-risk; Executive AI UI executes on approve |
| “Settings UI for DNA/twin” | **Business DNA** in Settings → AI; `digital_twin` still DB-only |
| “Cross-tenant learning shares data” | Patterns are anonymized platform rows; no private tenant data is copied |
| “Business simulation is Future” | **Outdated** — `SimulationService` + API + tests + `POST /reason` auto-simulate |
| “Intelligence API missing” | **Outdated** — `POST /api/company/intelligence/reason` shipped (Level 19) |

---

## Part K — Code map (quick index)

| Concern | Path |
|---------|------|
| WhatsApp entry | `app/Jobs/ProcessIncomingWhatsAppMessage.php` |
| Feature gate | `app/Services/Agent/CommerceAgentReplyService.php` |
| Chief agent | `app/Services/Agent/CommerceAgentOrchestrator.php` |
| Cognitive pipeline | `app/Services/Agent/Cognitive/CognitivePipelineService.php` |
| Tools | `app/Services/Agent/Tools/*` |
| Config | `config/agent.php` |
| Verify | `app/Console/Commands/VerifyAgentCommerceCommand.php` |
| Specialists | `app/Services/Agent/Specialists/*` |
| Event bus | `app/Services/Agent/Events/*` |
| Product graph | `app/Services/Agent/Company/ProductGraphService.php` |
| Routes | `routes/api.php` (company group) |
| Scheduler | `bootstrap/app.php` |
| Tests | `tests/Feature/CommerceAgent*.php` (63 CommerceAgent tests) |

---

## Part L — Digital Nervous System (strategic master)

Product thesis, all **24 vision concepts**, **Business Consciousness Layer**, signal inventory, honest status matrix, and Phases 5–10 roadmap:

**[SAVIT — The Digital Nervous System of a Business](SAVIT_DIGITAL_NERVOUS_SYSTEM.md)**

Consciousness today: hourly `RunBackgroundThinkingJob` + `ProcessAgentProactiveEventsJob`; daily brief; `UnifiedCompanyBrainService`; `OwnerAnalyticsAgentService`.

---

## Related documents

| Doc | Scope |
|-----|-------|
| [SAVIT_DIGITAL_NERVOUS_SYSTEM.md](SAVIT_DIGITAL_NERVOUS_SYSTEM.md) | **Strategic master** — nervous system, concepts 1–24, consciousness |
| [AI_PHASE4_OS.md](AI_PHASE4_OS.md) | Vision, brain, owner analytics, external tools |
| [AI_AGENT_OS.md](AI_AGENT_OS.md) | Tools, memory, WhatsApp loop, proactive |
| [AI_COMPANY_OS.md](AI_COMPANY_OS.md) | Reasoning, twin, intent, briefs, reflection |
| [AI_PLATFORM_OS.md](AI_PLATFORM_OS.md) | World model, trust, opportunities, Executive AI |
| [AI_COGNITIVE_OS.md](AI_COGNITIVE_OS.md) | Perception, debate, confidence, DNA, simulation |

**Last verified:** 2026-07-11 — `agent:verify` all OK (18 tools, 34 schema checks); **63** CommerceAgent tests green; Phase 4 vision, brain, owner analytics verified.
