---
title: AI Cognitive Architecture
parent: Home
nav_order: 10
description: Verified Layer 4 — perception, debate, confidence, critique, DNA, strategic memory, simulation, workforce.
---

# AI Cognitive Architecture (Layer 4)

**Status:** Production foundation — verified by `CommerceAgentCognitiveTest` (12 tests) and `agent:verify`.

**Master reference:** [Artificial Business Intelligence (ABI) Platform](AI_ABI_PLATFORM.md) — includes ABI Levels 1–20 mapping.

This layer answers: *How does the AI think — not only answer?*

---

## The shift

| Old question | New question |
|--------------|--------------|
| How do I make the AI answer better? | How do I make the AI **think** better? |

Humans do not use one brain process. SAVIT’s cognitive architecture separates **perception, debate, reasoning, decision, action, critique, and memory** so each can improve independently.

---

## Architecture (implemented)

```text
                 Conscious Mind (Chief Executive AI)
                      │
      ┌───────────────┼───────────────┐
      │               │               │
 Perception      Reasoning      Decision
 PerceptionSvc   ReasoningEng   ConfidenceSvc
      │               │               │
 Internal Debate (Sales / Support / Finance / CS / Chief)
      └───────────────┼───────────────┘
                      │
                Action System (18 tools)
                      │
                 Self-Critique
                      │
     Strategic Memory + Knowledge Compression + Meta Patterns
                      │
              Trust + Governance Layer
```

**Reply route prefix:** `agent_cognitive*`

**Pipeline class:** `App\Services\Agent\Cognitive\CognitivePipelineService`

---

## Pipeline step-by-step (`processTurn`)

| # | Step | Class | LLM? | Output |
|---|------|-------|------|--------|
| 1 | Perceive | `PerceptionService` | No | emotion, topic, urgency, risk, relationship |
| 2 | Reason | `ReasoningEngineService` | Yes (if enabled) | trace + prompt_block |
| 3 | Debate | `InternalDebateService` | No (uses trace + rules) | role → note map |
| 4 | Score | `ConfidenceScoringService` | No | float + action |
| 5 | Assemble prompt | DNA, perception, debate, economic, meta, confidence, reasoning | — | `prompt_block` |
| 6 | Govern | `GovernanceService` | No | policy metadata |
| 7 | Persist | `CognitiveEpisode` | — | `cognitive_episodes` row |

**Finalize:** `finalizeEpisode($id, $critique, $outcome)` after critique / handoff / failure.

**Test:** `test_cognitive_pipeline_persists_episode`

---

## Perception (#37)

**Class:** `PerceptionService` (+ `MessageSentimentService`)

### Fields

| Field | Examples |
|-------|----------|
| `emotion` | disappointed, concerned, positive, neutral |
| `topic` | wrong product, refund, delivery, pricing, order status, product inquiry, payment, general |
| `urgency` | high, medium, low |
| `risk` | possible return, price negotiation, trust risk, low |
| `relationship` | new / existing / loyal customer (from paid order count) |

### Verified example

Message: `I wanted the black one 😔`

- emotion → `disappointed` (emoji / cue path)  
- topic → `wrong product`  
- risk → `possible return`  

**Test:** `test_perception_extracts_disappointed_wrong_product`

**Honest status:** Rule-based. Roadmap: optional LLM enrichment pass.

---

## Internal debate (#38)

**Class:** `InternalDebateService`  
**Depends on:** `EconomicReasoningService` for finance voice; `CommerceSpecialistOrchestrator` for Sales/Support/Inventory consults per turn

Roles: `sales`, `support`, `finance`, `customer_success`, `chief`  
Uses reasoning `specialist_council` when present; otherwise rule perspectives from topic/risk/emotion. When `agent.specialists.consult_on_turn` is enabled, injects outputs from `SalesSpecialistService`, `SupportSpecialistService`, and `InventorySpecialistService`.

Injected as “leadership meeting — never reveal to customer.”

---

## Confidence scoring (#39)

**Class:** `ConfidenceScoringService`

### Scoring inputs (simplified)

- Reasoning trace present / understanding / plan → raise  
- `missing_info` → lower  
- Known topic → raise  
- High urgency + non-low risk → lower  
- Disappointed emotion / trust risk → lower  

Clamped to **0.10–0.99**.

### Actions

| Score | Action | Config |
|-------|--------|--------|
| ≥ 0.70 | `auto_respond` | `cognitive.confidence_auto_respond` / `AGENT_CONFIDENCE_AUTO` |
| ≥ 0.45 | `clarify` | `cognitive.confidence_clarify` / `AGENT_CONFIDENCE_CLARIFY` |
| < 0.45 | `escalate` | Orchestrator sets handoff |

---

## Self-critique (#40)

**Class:** `SelfCritiqueService`

Rule checks before send:

- Disappointed customer without empathy → prepend apology  
- Refund/return topic without resolution language → flag issue  
- Over-long reply → truncate (`max_output_chars` default 1200; config key optional)  
- Leak phrases (`internal reasoning`, `specialist council`, etc.) → flag  

**Test:** `test_self_critique_adds_empathy_for_disappointed_customer`

**Honest status:** Rule-based. Roadmap: LLM critic.

---

## Business DNA (#41)

**Class:** `BusinessDnaService`  
**Column:** `company_settings.business_dna` (JSON)

Falls back to `config('agent.cognitive.business_dna_defaults')` by industry (`retail`, `restaurant`, `services`, `other`).

Typical keys: `tone`, `values`, `risk_tolerance`, `service_philosophy`, `escalation_culture`, `communication_style`.

Also mentions configured `ai_tone` from settings.

**Dashboard:** Settings → AI → Business DNA (presets: luxury brand, friendly café, industry default, custom).

**API:** `GET/PUT /api/company/settings` — `businessDna`, `businessDnaPresets`, `agentCommerceEnabled`.

**Not in Settings API yet** — set via DB / future UI.

---

## Strategic memory (#42)

**Class:** `StrategicMemoryService`  
**Table:** `strategic_memories`

Stores **tactics**, not chat transcripts: strategy type, context, outcome, `success_score` (0–100), evidence JSON.

Injected into Chief prompt ordered by success score.

**Test:** `test_strategic_memory_stored_and_retrieved`

**API:** `GET /api/company/cognitive-ai/strategic-memories`

---

## Meta learning & collective patterns (#43, #55)

**Class:** `MetaLearningService`  
**Table:** `platform_intelligence_patterns` (no `company_id` — platform-wide)

Seeded on migrate from `cognitive.platform_patterns_seed`:

- Fast reply → higher conversion  
- Bundling → higher AOV  

Guidance injected without exposing tenant private data.

**Test:** `test_platform_patterns_seeded_without_tenant_data`

---

## Tool proposals (#44)

**Class:** `ToolProposalService`  
**Table:** `tool_proposals`

Detects repeated tool chains across chats (lookback 30 days). If a chain appears ≥ 2 times, creates/updates a `proposed` workflow for developer review.

Runs inside hourly `RunBackgroundThinkingJob`.

**Test:** `test_tool_proposal_detects_repeated_chain`

**Missing:** Publish → register as real `AgentTool`.

---

## Knowledge compression (#45)

**Class:** `KnowledgeCompressionService`  
**Table:** `knowledge_artifacts`

Example detector: ≥ 3 customer messages in 90 days about “difference / which one / model / confus*” → draft `comparison_guide` artifact.

**Test:** `test_knowledge_compression_creates_artifact_from_confusion`

---

## Digital workforce (#46)

**Class:** `DigitalWorkforceRegistry`  
**Config:** `agent.cognitive.workforce`

Seven directors (CEO, Sales, Finance, Marketing, Support, Operations, Inventory) with objectives and report descriptions.

Exposed on cognitive dashboard — **metadata today**, not separate running agents.

---

## Executive planning (#47)

**Class:** `ExecutivePlanningService`  
**Table:** `executive_plans`

`createPlan($company, $goalStatement)` — e.g. “double revenue” → streams assigned to directors + KPI targets.

**API:** `POST /api/company/cognitive-ai/plans` body `{ "goal": "..." }`

**Test:** `test_executive_plan_breaks_down_revenue_goal`

---

## Economic reasoning (#48)

**Class:** `EconomicReasoningService`

Computes prompt notes from:

- Average paid order (90d)  
- Customer LTV (sum paid for phone)  
- High-stock product count  

Feeds finance debate voice and Chief economic context.

---

## Forecast (#49)

**Class:** `ForecastService`

Compares last 7 vs previous 7 paid order counts → trend `growing` / `declining` / `stable` → `forecast_orders_7d`.

Returned on cognitive dashboard.

---

## Causal reasoning (#50)

**Class:** `CausalReasoningService`

Compares 14d vs prior 14d paid revenue → `dropped` / `increased` / `stable` / `unknown`.

If dropped, proposes likely causes (stock shortage, marketing gap, seasonality, price increase) with likelihood labels.

---

## Simulation (#54)

**Class:** `SimulationService`  
**Table:** `cognitive_simulations`

Scenario types: `discount`, `marketing_campaign`, generic.

Discount scenarios estimate revenue/profit/repeat/inventory-clear days for discount vs bundle vs no-discount.

**API:** `POST /api/company/cognitive-ai/simulate`  
Body: `{ "scenario_type": "discount", "inputs": { "discount_pct": 10 } }`

**Test:** `test_simulation_compares_discount_scenarios`

---

## Governance (#53)

**Class:** `GovernanceService`

Builds context: confidence action, human_approval_required, policies (high-risk tools, no unapproved discounts, explain before recommend), audit field list.

Enriched into trust log `explainability`.

---

## Cognitive APIs

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/company/cognitive-ai/dashboard` | Workforce, forecast, causal, episode, counts |
| GET | `/api/company/cognitive-ai/strategic-memories` | Tactics list |
| POST | `/api/company/cognitive-ai/plans` | Goal → plan |
| POST | `/api/company/cognitive-ai/simulate` | Scenario comparison |

**Controller:** `App\Http\Controllers\Api\Company\CognitiveAiController`  
**Test:** `test_cognitive_dashboard_api`

---

## Pillars #36–#55 status (verified)

| # | Pillar | Status |
|---|--------|--------|
| 36 | Cognitive architecture | **Implemented** |
| 37 | Perception | **Implemented** (rule-based) |
| 38 | Internal debate | **Implemented** |
| 39 | Confidence scoring | **Implemented** |
| 40 | Self-critique | **Implemented** (rule-based) |
| 41 | Business DNA | **Implemented** |
| 42 | Strategic memory | **Implemented** |
| 43 | Meta learning | **Implemented** (seeded patterns) |
| 44 | AI creates tools | **Implemented** (proposals) |
| 45 | Knowledge compression | **Implemented** (lite detectors) |
| 46 | Digital workforce | **Implemented** (registry) |
| 47 | Executive planning | **Implemented** |
| 48 | Economic reasoning | **Implemented** |
| 49 | Future prediction | **Implemented** (7d trend) |
| 50 | Causal reasoning | **Implemented** (lite) |
| 51 | Business OS | **Foundation** (all layers) |
| 52 | Intelligence marketplace | **Partial** |
| 53 | AI governance | **Implemented** (foundation) |
| 54 | Continuous simulation | **Implemented** |
| 55 | Collective intelligence | **Implemented** (anonymized patterns) |

For ABI Levels 1–20 (Understanding → Intelligence Loop), see **[AI_ABI_PLATFORM.md Part G](AI_ABI_PLATFORM.md)**.

---

## Migrations (Layer 4)

`2026_07_11_160000_create_ai_cognitive_tables.php`:

- `company_settings.business_dna`
- `cognitive_episodes`
- `strategic_memories`
- `tool_proposals`
- `platform_intelligence_patterns` (+ seed)
- `executive_plans`
- `knowledge_artifacts`
- `cognitive_simulations`

---

## Orchestrator integration

`CommerceAgentOrchestrator`:

1. Calls `CognitivePipelineService::processTurn`  
2. Injects cognitive + strategic memory into system prompt  
3. Escalates on low confidence  
4. Runs tools  
5. `SelfCritiqueService` + reply guard  
6. `AgentTrustService` with real confidence + governance  
7. Routes `agent_cognitive*`

**Test:** `test_orchestrator_uses_cognitive_route_and_trust_confidence`

---

## Verification

```bash
php artisan migrate --force
php artisan agent:verify
php artisan test --filter=CommerceAgentCognitive
php artisan test --filter=CommerceAgent
```

### Manual smoke

1. Enable agent mode  
2. Optionally set `business_dna` JSON on `company_settings`  
3. WhatsApp: `I wanted the black one 😔` — expect empathy  
4. Inspect `cognitive_episodes.perception`  
5. Inspect `agent_trust_logs.confidence`  
6. `GET /api/company/cognitive-ai/dashboard`  
7. `POST /api/company/cognitive-ai/simulate` with discount scenario  
8. `POST /api/company/cognitive-ai/plans` with a revenue goal  

---

## Code map

| Service | Path under `app/Services/Agent/Cognitive/` |
|---------|-------------------------------------------|
| Pipeline | `CognitivePipelineService.php` |
| Perception | `PerceptionService.php` |
| Debate | `InternalDebateService.php` |
| Confidence | `ConfidenceScoringService.php` |
| Critique | `SelfCritiqueService.php` |
| DNA | `BusinessDnaService.php` |
| Strategic memory | `StrategicMemoryService.php` |
| Meta learning | `MetaLearningService.php` |
| Tool proposals | `ToolProposalService.php` |
| Knowledge | `KnowledgeCompressionService.php` |
| Workforce | `DigitalWorkforceRegistry.php` |
| Planning | `ExecutivePlanningService.php` |
| Economic | `EconomicReasoningService.php` |
| Forecast | `ForecastService.php` |
| Causal | `CausalReasoningService.php` |
| Simulation | `SimulationService.php` |
| Governance | `GovernanceService.php` |

---

## Roadmap (Layer 4 → ABI)

1. LLM perception + LLM self-critique  
2. ~~Unified Intelligence API `POST /reason` (ABI Level 19)~~ — **shipped** (`POST /api/company/intelligence/reason`)  
3. ~~Approval execution UI~~ — **shipped** (Executive AI dashboard, Phase 5)  
4. ~~Voice perception for owner commands~~ — **shipped** (Phase 5 owner voice path)  
5. Publish tool proposals as real tools  
6. Calibrated Bayesian probabilities (ABI Level 4)  
7. Multi-year planning + org chart (ABI Levels 6–7)  
8. Digital board sessions (ABI Level 14)  
9. Investigation case files with multi-step autonomous probes (Levels 2–3)  
10. Intelligence loop KPI controller (Level 20)  

See also: [ABI Levels Summary](ABI_LEVELS_SUMMARY.md) · [ABI Platform](AI_ABI_PLATFORM.md)
