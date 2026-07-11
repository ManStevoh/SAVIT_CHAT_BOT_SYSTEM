---
title: AI Company Operating System
parent: Home
nav_order: 8
description: Verified Layer 2 — reasoning engine, digital twin, intent chains, operating guides, morning brief.
---

# AI Company Operating System (Layer 2)

**Status:** Production foundation — verified by `CommerceAgentCompanyTest` and `agent:verify`.

**Master reference:** [Artificial Business Intelligence (ABI) Platform](AI_ABI_PLATFORM.md)

This layer answers: *Does the AI reason about the company before acting, track customer journeys, and brief the owner each morning?*

---

## Purpose

Move from “reply to this message” to **company-aware cognition**:

- Structured reasoning traces (hypotheses → plan)  
- Emotional signal on the chat  
- Intent / journey chains per customer  
- Digital twin prompt context  
- Self-improving operating guides from reflections  
- Daily commerce brief for the owner  

---

## Architecture

```text
Incoming message
    → MessageSentimentService (rule-based)
    → ReasoningEngineService (LLM JSON, if enabled)
         → agent_reasoning_traces
         → CustomerIntentChainService::advanceFromReasoning
    → (Cognitive layer wraps this — see AI_COGNITIVE_OS)
    → Chief tool loop
    → delayed ReflectOnConversationJob
         → ConversationReflectionService
         → AgentOperatingGuideService
Daily 07:00
    → GenerateDailyCommerceBriefJob
         → CommerceMorningBriefService
         → ExecutiveBriefService (Platform layer)
```

---

## Reasoning engine

**Class:** `App\Services\Agent\Company\ReasoningEngineService`

**Config:** `agent.company.reasoning_enabled` (`AGENT_REASONING_ENABLED`, default `true`)

When disabled (tests often set this false for speed), returns sentiment guidance only — no LLM call.

### LLM JSON contract (requested)

```json
{
  "understanding": "...",
  "hypotheses": ["..."],
  "options": [{"label":"A","approach":"...","pros":"...","cons":"..."}],
  "chosen_plan": "...",
  "missing_info": ["..."],
  "specialist_council": {
    "sales": "...",
    "support": "...",
    "logistics": "..."
  },
  "time_context": "...",
  "geo_context": "..."
}
```

### Persistence

Table `agent_reasoning_traces`:

- `company_id`, `chat_id`, `incoming_message`
- `trace` (JSON), `chosen_plan`, `latency_ms`, `created_at`

### Prompt injection

`formatForChiefAgent()` builds an **internal** block (never for customer eyes): understanding, plan, council, time/geo, sentiment guidance.

**Test:** `CommerceAgentCompanyTest::test_reasoning_engine_stores_trace_and_updates_intent_chain`

---

## Sentiment

**Class:** `App\Services\Agent\Company\MessageSentimentService`

- **No LLM** — keyword / cue scoring  
- Labels: `frustrated`, `concerned`, `positive`, `neutral`  
- Writes `chats.detected_sentiment`  
- Provides `guidanceForPrompt()` for Chief  

**Test:** `test_sentiment_detects_frustration`

---

## Customer intent chains

**Class:** `App\Services\Agent\Company\CustomerIntentChainService`  
**Table:** `customer_intent_chains`

Tracks per-company + phone:

- `primary_intent`, `stage`, `journey` (JSON), `last_active_at`

Advanced from reasoning traces; also used for reorder signals in proactive job.

Prompt: `getForPrompt($company, $phone)`

---

## Digital twin

**Class:** `App\Services\Agent\Company\CompanyDigitalTwinService`  
**Column:** `company_settings.digital_twin` (JSON)

Injected into Chief system prompt via `getForPrompt($company)`.

**Note:** Not exposed on Settings GET/PUT API yet — set via DB / future UI. Column verified by `agent:verify`.

---

## Operating guides (self-improving prompts)

| Piece | Detail |
|-------|--------|
| Table | `agent_operating_guides` |
| Service | `AgentOperatingGuideService` |
| Writer | `ConversationReflectionService` via `ReflectOnConversationJob` |
| Limit | `agent.company.operating_guide_limit` (8) |

After conversations, the system runs a **full LLM reflection** and stores durable guidance (sales playbook fragments, support patterns).

Outputs stored in `agent_reflections.metadata`:
- `satisfaction_score` (0–1)
- `improvement_notes` (actionable coaching)
- `reflection_type: conversation_review`

Also upserts `agent_operating_guides` when the reflection identifies durable patterns.

**Test:** `test_reflection_creates_operating_guide`

**Delay:** `agent.proactive.reflection_delay_minutes` (default 45)

---

## Knowledge graph

**Classes:** `CommerceKnowledgeGraphService` (customer traversal), `ProductGraphService` (product edges)  
**Table:** `product_relationships` — types: `accessory`, `warranty`, `bundle`, `complement`, `replacement`  
**Tools:** `trace_customer_graph`, `get_product_relationships`

Customer → orders → products traversal plus product ↔ accessory ↔ warranty relationships.

**Not yet:** supplier / warehouse / courier edges, external graph DB.

**Tests:** `test_trace_customer_graph_links_orders_to_catalog`, `CommerceAgentPhase3Test` (graph + tool)

---

## Morning commerce brief

**Class:** `App\Services\Agent\Company\CommerceMorningBriefService`  
**Job:** `GenerateDailyCommerceBriefJob` daily at **07:00**  
**Table:** `commerce_briefs` (`brief_date`, `summary`, `metrics`, `recommendations`, `executive_decisions`)

**API:** `GET /api/company/commerce-brief` → today’s brief (does not regenerate if already exists for the date)

Platform layer attaches **executive decisions** via `ExecutiveBriefService::attachToBrief`.

**Tests:** `test_morning_brief_persists_for_company`, `test_commerce_brief_api_returns_today_brief`

---

## Council flag (honest status)

Column `company_settings.agent_council_enabled` exists (migration + model fillable/casts).

**Not wired:** no service currently branches on this flag. Specialist council text comes from the reasoning LLM JSON and/or `InternalDebateService` (Cognitive layer) regardless.

---

## Migrations (Layer 2)

`2026_07_11_140000_create_ai_company_tables.php`:

- `digital_twin`, `agent_council_enabled`
- `chats.detected_sentiment`
- `agent_reasoning_traces`, `customer_intent_chains`, `agent_operating_guides`, `commerce_briefs`

---

## Config (`agent.company`)

| Key | Default | Purpose |
|-----|---------|---------|
| `reasoning_enabled` | true | Toggle LLM reasoning pass |
| `operating_guide_limit` | 8 | Prompt injection cap |
| `low_stock_threshold` | 5 | Used by Platform opportunities / world model |
| `reorder_threshold_ratio` | 0.85 | Intent reorder heuristics |

---

## Verification

```bash
php artisan test --filter=CommerceAgentCompany
php artisan agent:verify
```

### Manual smoke

1. Enable agent mode + reasoning (default on)  
2. Send a frustrated message; check `chats.detected_sentiment`  
3. Check `agent_reasoning_traces` for a row  
4. Wait for reflection job or dispatch `ReflectOnConversationJob`  
5. Call `GET /api/company/commerce-brief` after brief job  

---

## Code map

| Path | Role |
|------|------|
| `Company/ReasoningEngineService.php` | Structured reasoning |
| `Company/MessageSentimentService.php` | Sentiment |
| `Company/CustomerIntentChainService.php` | Journey |
| `Company/CompanyDigitalTwinService.php` | Twin prompt |
| `Company/AgentOperatingGuideService.php` | Guides |
| `Company/ConversationReflectionService.php` | Reflection |
| `Company/CommerceMorningBriefService.php` | Brief |
| `Company/CommerceKnowledgeGraphService.php` | Customer graph |
| `Company/ProductGraphService.php` | Product relationships |
| `Jobs/Agent/ReflectOnConversationJob.php` | Delayed reflection |
| `Jobs/Agent/GenerateDailyCommerceBriefJob.php` | Daily brief |
| `Http/Controllers/Api/Company/CommerceBriefController.php` | Brief API |

---

## Roadmap (Layer 2 — remaining)

1. Wire `agent_council_enabled` to force multi-specialist debate  
2. Expose `digital_twin` in Settings API + UI  
3. Supplier / warehouse graph edges  
4. Multi-modal perception (images / voice) — see Cognitive roadmap  
5. Owner analytics agent (“why are sales down?”) — overlaps ABI Levels 1–3  

See also: [AI Agent OS](AI_AGENT_OS.md) · [AI Platform OS](AI_PLATFORM_OS.md) · [AI Cognitive OS](AI_COGNITIVE_OS.md) · [ABI Platform](AI_ABI_PLATFORM.md)
