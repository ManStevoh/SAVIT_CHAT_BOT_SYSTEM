---
title: SAVIT — The Digital Nervous System of a Business
parent: Home
nav_order: 5
description: Strategic and technical master reference — from WhatsApp commerce to continuously thinking business intelligence. Maps vision concepts 1–24 to verified code.
---

# SAVIT — The Digital Nervous System of a Business

**Verified against production code on 2026-07-11.**

> **Old framing:** WhatsApp + AI + E-commerce = SAVIT  
> **New framing:** **SAVIT = The Digital Nervous System of a Business**

Once you think this way, **every piece of business information becomes a signal the AI can understand** — not rows in tables waiting for a human to query them.

This document is the **strategic north star** and **honest implementation map** for that vision. It connects product concepts (1–24) and the **Business Consciousness Layer** to real PHP services, database tables, jobs, APIs, and tests.

For layer-by-layer engineering detail, see [AI ABI Platform](AI_ABI_PLATFORM.md). For shipped phases, see [Phase 4 OS](AI_PHASE4_OS.md) and [Phase 5 OS](AI_PHASE5_OS.md).

| Verification (today) | Result |
|----------------------|--------|
| `php artisan agent:verify` | 18 tools, 34 schema checks — OK |
| `php artisan test --filter=CommerceAgent` | 63 tests passing |
| Continuous cognition | Hourly `RunBackgroundThinkingJob` + post-chat delayed jobs |

---

## The nervous system model

A biological nervous system has **sensors**, **signals**, **memory**, **reflexes**, and **conscious attention**. SAVIT maps these as follows:

```mermaid
flowchart TB
    subgraph Sensors["Sensors (inputs)"]
        WA[WhatsApp messages + images]
        GR[Growth / attribution / campaigns]
        OR[Orders / payments / inventory]
        OW[Owner questions / dashboard]
    end

    subgraph Signals["Signal bus"]
        EV[commerce_agent_events]
        WM[business_world_snapshots]
        BR[company_brain_snapshots]
    end

    subgraph Memory["Memory layers"]
        CM[customer_memories]
        OM[organizational_memories]
        SM[strategic_memories]
        OG[agent_operating_guides]
        KG[product_relationships + graphs]
    end

    subgraph Reflexes["Reflexes (automated)"]
        PR[ProcessAgentProactiveEventsJob]
        BG[RunBackgroundThinkingJob]
        SP[Specialist background pipeline]
    end

    subgraph Conscious["Consciousness (attention)"]
        BF[Commerce morning brief]
        EX[Executive brief + decisions]
        OA[Owner analytics investigations]
        TL[Agent trust logs / explainability]
    end

    Sensors --> Signals
    Signals --> Memory
    Memory --> Reflexes
    Reflexes --> Conscious
    Conscious --> Chief[Chief Executive AI + 18 tools]
    Chief --> Sensors
```

**Key insight:** Most software waits for clicks. Most AI waits for questions. The nervous system **continuously senses, prioritizes, and prepares** — so when the owner opens the dashboard, hours of analysis are already done.

---

## Maturity legend

| Status | Meaning |
|--------|---------|
| **Implemented** | Real code + tables + tests or scheduled jobs |
| **Partial** | Foundation exists; missing depth, UI, or full loop |
| **Foundation** | Schema/services wired; not product-complete |
| **Roadmap** | Designed direction; not built |

---

## Concept map — all 24 + Consciousness

### 1. Business Graph (not just a database)

**Vision:** Every entity and relationship is traversable — suppliers → products → orders → customers — without hand-written SQL joins.

| Status | **Partial → growing** |
|--------|------------------------|
| Today | Customer → orders → products (`CommerceKnowledgeGraphService`, `trace_customer_graph` tool). Product ↔ accessory ↔ warranty (`ProductGraphService`, `product_relationships`, `get_product_relationships` tool). |
| Missing | Employees, suppliers, warehouses, campaigns, contracts, competitors as first-class graph nodes and edges. Graph query language / UI. |
| Code | `app/Services/Agent/Company/CommerceKnowledgeGraphService.php`, `ProductGraphService.php` |
| Tables | `product_relationships`, orders, products, `customer_intent_chains` |

---

### 2. AI Timeline

**Vision:** The company’s life as a chronological narrative — branch opened → revenue milestone → supplier change → sales drop → campaign → recovery.

| Status | **Partial** |
|--------|-------------|
| Today | Timestamped events: orders, `commerce_agent_events`, `commerce_briefs`, `business_world_snapshots`, `owner_analytics_investigations`, `cognitive_episodes`, trust logs. |
| Missing | Unified `business_timeline` model, milestone types, owner-facing timeline UI, causal linking (“supplier changed” → “sales dropped”). |
| Code | `CommerceEventDetector`, `CommerceMorningBriefService`, `OwnerAnalyticsAgentService` |

---

### 3. Business DNA

**Vision:** On signup, AI learns tone, products, pricing, values, competitors, strengths — not forms.

| Status | **Partial** |
|--------|-------------|
| Today | `company_settings.business_dna` JSON + `BusinessDnaService` industry defaults (tone, values, risk, escalation). Injected into cognitive pipeline and Chief prompt. |
| Missing | Automated discovery on signup; owner interview flow; DNA versioning UI. Settings API does not expose `business_dna` yet. |
| Code | `app/Services/Agent/Cognitive/BusinessDnaService.php` |
| Config | `config/agent.php` → `cognitive.business_dna_defaults` |

---

### 4. AI Interviews the Business

**Vision:** Conversational onboarding — “Tell me about your business” → natural follow-ups → auto-built business model.

| Status | **Roadmap** |
|--------|-------------|
| Today | Static company settings + industry field + optional `digital_twin` JSON (manual/DB). |
| Missing | `OnboardingInterviewService`, multi-turn owner chat session, structured extraction → DNA + twin. |
| Adjacent | WhatsApp agent could host interview once owner channel is defined. |

---

### 5. AI Observability

**Vision:** Every recommendation expandable: data used → reasoning → confidence → alternatives → expected outcome.

| Status | **Partial** |
|--------|-------------|
| Today | `agent_trust_logs` with `reasoning_summary`, `tools_used`, `data_consulted`, `confidence`, `explainability` JSON. `agent_reasoning_traces`, `cognitive_episodes`, `agent_tool_invocations` audit trail. |
| Missing | Owner UI “Why did AI recommend this?” on every surface. Standard explainability card component. |
| Code | `AgentTrustService::logDecision()`, `ReasoningEngineService` |
| API | Trust data on executive dashboard; not yet on every customer reply. |

---

### 6. AI Meetings (morning COO briefing)

**Vision:** Every morning: revenue ↑, tickets ↓, inventory risk, marketing performance, one recommendation.

| Status | **Implemented** |
|--------|-----------------|
| Today | `GenerateDailyCommerceBriefJob` (07:00) → `CommerceMorningBriefService` + `ExecutiveBriefService` top decisions. `GET /api/company/commerce-brief`. |
| Partial | No voice/meeting UI; marketing section depends on Growth data richness. |
| Code | `CommerceMorningBriefService`, `ExecutiveBriefService`, `GenerateDailyCommerceBriefJob` |
| Table | `commerce_briefs` |

---

### 7. AI Whiteboard (expansion planning workspace)

**Vision:** Owner types “expand to Uganda” → structured workspace: market, legal, hiring, taxes, inventory, budget, timeline.

| Status | **Partial** |
|--------|-------------|
| Today | `ExecutivePlanningService` → `executive_plans` with director work streams. `POST /api/company/cognitive-ai/plans`. `SimulationService` for scenarios. |
| Missing | Interactive whiteboard UI, geo-expansion templates, collaborative editing. |
| Code | `ExecutivePlanningService`, `SimulationService`, `CognitiveAiController` |

---

### 8. AI Mission Control

**Vision:** One screen — revenue, customers, orders, inventory, marketing, cash flow, alerts — AI highlights only what needs attention.

| Status | **Partial** |
|--------|-------------|
| Today | `GET /api/company/executive-ai/dashboard`, `GET /api/company/cognitive-ai/dashboard`, `GET /api/company/company-brain`, Growth analytics APIs. Health score, opportunities, open events. |
| Missing | Unified Mission Control frontend; single attention-priority queue across subsystems. |
| Code | `ExecutiveAiController`, `CognitiveAiController`, `CompanyBrainController`, `BusinessHealthScoreService` |

---

### 9. AI Memory Search

**Vision:** “What happened three months ago when sales dropped?” — search chats, invoices, campaigns, reports, support logs.

| Status | **Partial** |
|--------|-------------|
| Today | `OwnerAnalyticsAgentService` correlates orders + growth + events + brain digest. Vector search on knowledge/products (`KnowledgeChunkService`, `VectorSimilarity`). Customer memories, org/strategic memories. |
| Missing | Cross-source semantic search UI; email/meeting ingestion; unified memory index. |
| Code | `OwnerAnalyticsAgentService`, `SearchKnowledgeTool`, `CustomerMemoryService` |

---

### 10. AI Relationship Intelligence

**Vision:** Full customer relationship graph — bought → complained → referred → tickets → negotiated → returned → bought again.

| Status | **Partial** |
|--------|-------------|
| Today | `trace_customer_graph` tool, `CustomerIntentChainService`, `customer_memories`, sentiment on `chats.detected_sentiment`, order history in profile tool. |
| Missing | Explicit relationship edge types (referred, negotiated, returned); CRM-style relationship score; referral chain visualization. |
| Code | `CommerceKnowledgeGraphService`, `CustomerIntentChainService`, `GetCustomerProfileTool` |

---

### 11. AI Digital Twin

**Vision:** Clone the company; simulate “what if prices +8%?” before deciding.

| Status | **Partial** |
|--------|-------------|
| Today | `company_settings.digital_twin` JSON + `CompanyDigitalTwinService` (mission, brand, strategy, competitors). `SimulationService` + `POST /api/company/cognitive-ai/simulate`. |
| Missing | Twin auto-sync from live data; price-change simulation wired to catalog; twin dashboard. |
| Code | `CompanyDigitalTwinService`, `SimulationService` |

---

### 12. AI Continuous Audits

**Vision:** Always-on internal auditor — duplicate customers, wrong pricing, fraud, inventory mismatch, unpaid invoices, broken workflows.

| Status | **Partial** |
|--------|-------------|
| Today | `OpportunityDetectionService` (bundles, restock, slow movers). `CommerceEventDetector` (low_stock, sales_drop, delivery_delay, birthday). Health score factors. |
| Missing | Duplicate customer detection, fraud models, permission audits, invoice reconciliation agent. |
| Code | `OpportunityDetectionService`, `CommerceEventDetector`, `BusinessHealthScoreService` |

---

### 13. AI Knowledge Mining

**Vision:** 100k chats → discover “customers misunderstand delivery fees” → recommend pricing/FAQ/checkout fixes.

| Status | **Partial** |
|--------|-------------|
| Today | `KnowledgeCompressionService` → `knowledge_artifacts`. `ConversationReflectionService` → operating guides. `ToolProposalService` for repeated patterns. Memory extraction job. |
| Missing | Aggregate pattern mining at scale; auto-FAQ PRs; structured improvement tickets. |
| Code | `KnowledgeCompressionService`, `ConversationReflectionService`, `ExtractCustomerMemoriesJob` |
| Tables | `knowledge_artifacts`, `agent_operating_guides`, `tool_proposals` |

---

### 14. AI Business Coach

**Vision:** Weekly: three wins, three risks, three opportunities, one recommendation.

| Status | **Partial** |
|--------|-------------|
| Today | Daily commerce brief + executive decisions. `GrowthStrategyService::generateWeeklyBrief` (Growth module). Owner analytics investigations. |
| Missing | Unified weekly coach format across Commerce + Growth; scheduled coach digest to owner. |
| Code | `CommerceMorningBriefService`, `OwnerAnalyticsAgentService`, Growth `GrowthStrategyService` |

---

### 15. AI Team Coach

**Vision:** Per-employee coaching — close rate, negotiation loss patterns, recommendations.

| Status | **Roadmap** |
|--------|-------------|
| Today | Company-level agents only; no per-employee performance model. |
| Missing | Employee entities, performance signals, team coach service, HR privacy gates. |

---

### 16. AI Operating Procedures (SOPs)

**Vision:** Employees ask “How do I process returns?” — AI answers from company SOPs; update once, everyone gets latest.

| Status | **Partial** |
|--------|-------------|
| Today | `agent_operating_guides` from reflection. `search_faq`, `search_knowledge` tools. Operating guide injection into Chief prompt. |
| Missing | Formal SOP editor, versioning, role-based SOP access, employee-facing channel (not only WhatsApp customers). |
| Code | `AgentOperatingGuideService`, `SearchFaqTool`, `SearchKnowledgeTool` |

---

### 17. AI Project Manager

**Vision:** “Launch new product” → tasks, timeline, dependencies, risks, owners, progress.

| Status | **Partial** |
|--------|-------------|
| Today | `ExecutivePlanningService` breaks goals into work streams → `executive_plans`. Cognitive plans API. |
| Missing | Task graph, dependencies, assignees, Gantt/board UI, progress telemetry. |
| Table | `executive_plans` |

---

### 18. AI Goal Tracking

**Vision:** Goal: Revenue 50M → current 32M → gap 18M → recommendations.

| Status | **Partial** |
|--------|-------------|
| Today | `company_settings.agent_business_goals` + `BusinessGoalService` in Chief prompt. World model `goals[]`. Executive plans KPI targets. Owner analytics compares revenue periods. |
| Missing | Persistent goal progress dashboard, automatic gap analysis each morning, goal-linked action queue. |
| Code | `BusinessGoalService`, `BusinessWorldModelService`, `OwnerAnalyticsAgentService` |

---

### 19. AI Business Simulator

**Vision:** Before hiring / pricing / branching — simulate profit, risk, cash flow, ROI.

| Status | **Implemented (foundation)** |
|--------|------------------------------|
| Today | `SimulationService` compares discount vs bundle vs no-discount scenarios. `POST /api/company/cognitive-ai/simulate`. Tests in `CommerceAgentCognitiveTest`. |
| Missing | Hire/branch/cash-flow models; Monte Carlo; simulator UI for owners. |
| Code | `app/Services/Agent/Cognitive/SimulationService.php` |
| Table | `cognitive_simulations` |

---

### 20. AI Company Memory

**Vision:** Decisions, rationale, lessons, project history survive employee turnover.

| Status | **Partial** |
|--------|-------------|
| Today | `organizational_memories`, `strategic_memories` (tactics + outcomes), operating guides, knowledge artifacts, trust logs, investigations. |
| Missing | Decision provenance linking (who decided, measured outcome months later). Company memory search API. |
| Code | `OrganizationalMemoryService`, `StrategicMemoryService` |

---

### 21. AI Marketplace (industry intelligence modules)

**Vision:** Install School AI, Hospital AI, Retail AI — domain reasoning + tools.

| Status | **Foundation** |
|--------|----------------|
| Today | `SkillModuleRegistry` — retail, restaurant, services, other — prompt addons + tool hints in config. |
| Missing | Marketplace UI, install/uninstall, third-party modules, per-industry tool packs beyond config. |
| Code | `SkillModuleRegistry`, `config/agent.php` → `platform.skill_modules` |

---

### 22. AI Skill Learning (observe → document → assist)

**Vision:** “Teach SAVIT our procurement process” — observe once, document workflow, suggest improvements, later assist with approval.

| Status | **Roadmap** |
|--------|-------------|
| Today | `ToolProposalService` detects repeated tool chains; knowledge compression from confusion patterns. |
| Missing | Screen/workflow recorder, procedural memory store, approved automation replay. |

---

### 23. Multi-Channel Brain

**Vision:** WhatsApp + Instagram + Email + Web + POS + ERP → one unified AI brain, shared context.

| Status | **Partial** |
|--------|-------------|
| Today | WhatsApp primary loop. Growth attribution from social. `UnifiedCompanyBrainService` merges commerce + growth. Dashboard agent send. |
| Missing | Instagram/Messenger/email ingest channels, cross-channel session continuity, ERP/POS connectors as signal sources. |
| Code | `UnifiedCompanyBrainService`, `ProcessIncomingWhatsAppMessage`, Growth `AttributionService` |

---

### 24. AI Economy (subscribable agent capabilities)

**Vision:** Developers publish Negotiation Agent, Procurement Agent, Accountant — businesses subscribe à la carte.

| Status | **Roadmap** |
|--------|-------------|
| Today | 18 built-in tools, 3 specialists, skill modules as config — all first-party. |
| Missing | Agent SDK, billing per capability, third-party agent registry, sandbox + approval for external agents. |
| Foundation | `AgentToolRegistry` allowlist pattern is the execution bus external agents would use. |

---

## The Business Consciousness Layer

> **The feature that could make SAVIT one of the most unique AI business platforms.**

Every few minutes (today: hourly + post-chat), the system asks itself:

| Question | Today (verified behavior) |
|----------|---------------------------|
| What has changed in this business? | `BusinessWorldModelService::snapshot`, `UnifiedCompanyBrainService::buildSnapshot` |
| What deserves the owner's attention? | `CommerceEventDetector`, owner alerts → `company_notifications`, opportunities |
| What decisions are waiting? | `agent_action_requests` (approval queue; no high-risk tools yet) |
| What opportunities appeared? | `OpportunityDetectionService` |
| What risks are increasing? | Health score, sales_drop / low_stock events, causal notes in reasoning |
| Which goals are falling behind? | Goals in world model + owner analytics evidence |
| Which customers need proactive outreach? | Abandoned cart, delivery delay, birthday, reorder signals |
| What can I prepare before anyone asks? | Morning brief, brain snapshot, specialist background runs, reflection guides |

### Consciousness stack (implemented pieces)

```text
Schedule (bootstrap/app.php)
├── Hourly  RunBackgroundThinkingJob
│             → world snapshot, opportunities, health, brain refresh
│             → specialist pipeline, event detection
├── Hourly  ProcessAgentProactiveEventsJob
│             → abandoned carts, reorder, customer events, owner alerts
└── Daily   GenerateDailyCommerceBriefJob (07:00)
              → commerce brief + executive decisions

Post-chat (ProcessIncomingWhatsAppMessage)
├── ExtractCustomerMemoriesJob (delayed)
├── ReflectOnConversationJob (delayed)
└── RunBackgroundThinkingJob (delayed)

Owner-initiated
├── POST /api/company/owner-analytics/investigate
├── GET  /api/company/company-brain
└── GET  /api/company/executive-ai/dashboard
```

| Status | **Partial — loop exists; consciousness is not yet sub-minute or owner-UI-complete** |
|--------|----------------------------------------------------------------------------------------|
| Gap | Sub-minute consciousness interval; unified attention inbox; proactive push to owner (email/WhatsApp); measured outcome closure on every recommendation. |

**Competitive shift:** Reactive software → **continuously thinking software**. The owner logs in; the AI has already run analysis and surfaces only what matters.

---

## How this maps to shipped engineering

| Layer | Nervous system role | Doc |
|-------|---------------------|-----|
| Layer 1 Agent OS | Motor nerves — act via 20 tools, WhatsApp, vision, voice | [AI_AGENT_OS](AI_AGENT_OS.md) |
| Layer 2 Company OS | Short-term memory — reasoning, twin, briefs, graph | [AI_COMPANY_OS](AI_COMPANY_OS.md) |
| Layer 3 Platform OS | Vital signs — world model, health, trust, executive | [AI_PLATFORM_OS](AI_PLATFORM_OS.md) |
| Layer 4 Cognitive OS | Prefrontal cortex — debate, confidence, simulation | [AI_COGNITIVE_OS](AI_COGNITIVE_OS.md) |
| Phase 3 | Specialist workers + event bus + product graph | [AI_ABI_PLATFORM](AI_ABI_PLATFORM.md) § B.5 |
| Phase 4 | Vision + unified brain + owner analytics + external bus | [AI_PHASE4_OS](AI_PHASE4_OS.md) |
| Phase 5 | Executive UI + voice commands + approval execution + A/B experiments | [AI_PHASE5_OS](AI_PHASE5_OS.md) |

---

## Signal inventory — what the nervous system senses today

| Signal source | Table / service | Used by |
|---------------|-----------------|---------|
| WhatsApp text | `messages` | Chief agent, perception, memories |
| WhatsApp images | `messages` + `message_vision_analyses` | Vision pipeline → Chief |
| Orders / payments | `orders` | World model, analytics, tools |
| Products / stock | `products` | Graph, opportunities, events |
| Customer facts | `customer_memories` | Profile tool, proactive |
| Marketing / attribution | `attribution_events`, Growth services | Brain, marketing tool, briefs |
| Ad spend | `growth_ad_spend_entries` | Growth analytics, brain |
| Social posts | `social_posts` | Top posts in brain |
| Agent events | `commerce_agent_events` | Proactive + owner alerts |
| Reasoning traces | `agent_reasoning_traces` | Observability, episodes |
| Trust decisions | `agent_trust_logs` | Executive dashboard |

---

## Roadmap — toward full nervous system

Prioritized by leverage on the **consciousness layer**:

| Phase | Theme | Key deliverables |
|-------|-------|------------------|
| **5** | Business Graph v2 | Supplier, warehouse, campaign nodes; graph API; supplier→product queries |
| **5** | Business Timeline | `business_timeline_events` + milestone UI + owner analytics integration |
| **6** | Onboarding Interview | AI interviews owner → DNA + twin auto-population |
| **6** | Mission Control UI | Single attention inbox fed by brain + events + opportunities |
| **7** | Memory Search | Unified semantic index across chats, orders, campaigns, briefs |
| **7** | Observability UI | Explainability card on every AI recommendation |
| **8** | Multi-channel ingest | Email + Instagram DM + web widget → same `Chat` + brain |
| **9** | Consciousness v2 | 5-minute sense cycle; owner morning push; outcome tracking on recommendations |
| **10** | AI Marketplace | Installable industry modules + third-party agent SDK |

---

## API surface for nervous system (owner / integrator)

| Endpoint | Nervous system function |
|----------|-------------------------|
| `GET /api/company/company-brain` | Unified commerce + growth state |
| `POST /api/company/company-brain/refresh` | Force consciousness refresh |
| `POST /api/company/owner-analytics/investigate` | Ask the business a question with evidence |
| `GET /api/company/commerce-brief` | Daily COO-style briefing |
| `GET /api/company/executive-ai/dashboard` | Mission control metrics |
| `GET /api/company/executive-ai/opportunities` | Detected opportunities |
| `GET /api/company/cognitive-ai/dashboard` | Cognitive health + episodes |
| `POST /api/company/cognitive-ai/simulate` | Digital twin scenarios |
| `POST /api/company/cognitive-ai/plans` | Whiteboard / planning seeds |
| `GET /api/company/commerce-specialists/runs` | Background specialist consciousness |

Full REST catalog: [API Reference](api-reference.md) (agent routes being expanded).

---

## Verification commands

```bash
cd LARAVEL_BACKEND
php artisan migrate --force
php artisan agent:verify
php artisan test --filter=CommerceAgent
php artisan route:list --path=company/company-brain
php artisan route:list --path=company/owner-analytics
```

---

## Related documents

| Document | Purpose |
|----------|---------|
| [AI ABI Platform](AI_ABI_PLATFORM.md) | Engineering master — layers, tools, ABI levels |
| [AI Phase 4 OS](AI_PHASE4_OS.md) | Vision, brain, owner analytics |
| [AI Phase 5 OS](AI_PHASE5_OS.md) | Executive dashboard, approvals, voice, experiments |
| [Growth Engine](growth-engine.md) | Marketing signal source |
| [WhatsApp Bot](whatsapp-bot.md) | Primary customer nerve ending |

**Last verified:** 2026-07-11 — 63 CommerceAgent tests; 18 tools; consciousness jobs scheduled in `bootstrap/app.php`.
