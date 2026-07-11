---
title: AI Agent Commerce OS
parent: Home
nav_order: 7
description: Verified Layer 1 — tool server, Chief Agent loop, memory, WhatsApp integration, proactive outreach.
---

# AI Agent Commerce OS (Layer 1)

**Status:** Production foundation — verified by `php artisan agent:verify` and `CommerceAgent*` / WhatsApp tests.

**Master reference:** [Artificial Business Intelligence (ABI) Platform](AI_ABI_PLATFORM.md)

This layer answers: *Can the AI use tools, remember customers, and reply on WhatsApp like a commerce employee?*

---

## Purpose

Replace brittle scripted FAQ/order-only replies with an **allowlisted tool-using agent** that:

1. Reads company context and conversation history  
2. Calls commerce tools (search, catalog, orders, handoff, memory)  
3. Audits every tool call  
4. Falls back to legacy flows if the agent fails  

Higher layers (Company / Platform / Cognitive) plug into the same orchestrator without replacing WhatsApp integration.

---

## End-to-end WhatsApp flow

```text
Meta webhook
  → ProcessIncomingWhatsAppMessage
      → CommerceAgentReplyService::isEnabledForCompany?
           YES → CommerceAgentOrchestrator::run()
                 → reply_source = agent_cognitive | agent_cognitive_order | agent_cognitive_handoff
                 → schedulePostConversationJobs()
           NO  → legacy greeting / OrderFlowService / FAQ AI path
```

**Integration file:** `app/Jobs/ProcessIncomingWhatsAppMessage.php`

When agent mode is on and the orchestrator returns a non-empty reply, the job **does not** run the legacy opening/order path for that message.

If the agent returns empty / failed, the job continues into legacy handling (`test_whatsapp_job_falls_back_to_legacy_when_agent_returns_empty`).

---

## Feature flags & settings

| Setting | DB column | API field | Default |
|---------|-----------|-----------|---------|
| Agent commerce mode | `company_settings.agent_commerce_enabled` | `agentCommerceEnabled` | `false` |
| Proactive outreach | `company_settings.agent_proactive_enabled` | `agentProactiveEnabled` | `false` |
| Business goals | `company_settings.agent_business_goals` (JSON array of keys) | `agentBusinessGoals` | catalog defaults if empty |

**Endpoints:**

- `GET /api/company/settings`
- `PUT /api/company/settings`

**Catalog of goal keys** (`config/agent.php` → `business_goals`):

| Key | Meaning |
|-----|---------|
| `increase_revenue` | Helpful recommendations / upsells |
| `reduce_refunds` | Clarify policies / expectations |
| `increase_repeat_customers` | Loyalty |
| `improve_response_time` | Resolve quickly |
| `clear_old_inventory` | Promote slow movers |

**Service:** `App\Services\Agent\BusinessGoalService`

**Tests:** `CommerceAgentTest` (flags, goals), `CommerceAgentFlowTest::test_settings_api_returns_and_saves_agent_fields`

---

## Tool server

### Contract

`App\Services\Agent\Contracts\AgentTool`:

- `name(): string`
- `description(): string`
- `parametersSchema(): array` (JSON Schema for OpenAI tools)
- `execute(AgentToolContext $context, array $arguments): array`

### Registry

`App\Services\Agent\AgentToolRegistry` — allowlist only. Tools registered in `AppServiceProvider`.

`agent:verify` asserts **exactly 18** tools.

### Runner & audit

`App\Services\Agent\AgentToolRunner`:

1. If `AgentApprovalService::requiresApproval($toolName)` (risk === `high`) → queue `agent_action_requests`, return `{ pending_approval: true }` **without executing**
2. Else execute via registry
3. Truncate large results (`tool_result_max_chars`, default 8000)
4. Persist `agent_tool_invocations` (arguments, result, duration_ms, success)

### Tool catalog

| Tool | Class | Risk | Behavior |
|------|-------|------|----------|
| `search_products` | `SearchProductsTool` | low | Semantic + lexical product search |
| `search_faq` | `SearchFaqTool` | low | FAQ / policy lookup |
| `search_knowledge` | `SearchKnowledgeTool` | low | Knowledge chunk search |
| `get_customer_profile` | `GetCustomerProfileTool` | low | Memories + recent orders |
| `search_orders` | `SearchOrdersTool` | low | Lookup by order number |
| `get_catalog` | `GetCatalogTool` | low | Numbered catalog for ordering |
| `process_order_message` | `ProcessOrderMessageTool` | medium | Delegates to order/checkout flow |
| `transfer_to_human` | `TransferToHumanTool` | medium | Sets handoff |
| `remember_customer` | `RememberCustomerTool` | low | Upserts `customer_memories` |
| `get_business_info` | `GetBusinessInfoTool` | low | Hours, payments, timezone |
| `trace_customer_graph` | `TraceCustomerGraphTool` | low | Customer → orders → products |
| `get_product_relationships` | `GetProductRelationshipsTool` | low | Product graph edges (accessory, warranty, bundle) |
| `check_delivery_status` | `CheckDeliveryStatusTool` | low | Order shipping / delay status |
| `get_weather` | `GetWeatherTool` | low | Open-Meteo weather (no API key) |
| `check_mpesa_payment` | `CheckMpesaPaymentTool` | low | M-Pesa payment status by order |
| `get_shipping_quote` | `GetShippingQuoteTool` | low | Shipping quote + ETA |
| `check_calendar_availability` | `CheckCalendarAvailabilityTool` | low | Working hours / slots |
| `get_marketing_performance` | `GetMarketingPerformanceTool` | low | Growth metrics bridge |

**Context object:** `AgentToolContext` — company, chat, customer phone/name, incoming message.

**Tests:** `test_search_products_tool_finds_product_and_audits_invocation`, `test_remember_customer_tool_persists_memory`, `test_agent_tool_loop_records_multiple_invocations`, `test_trace_customer_graph_links_orders_to_catalog`

---

## Memory layers (Layer 1)

### Customer memory

| Piece | Detail |
|-------|--------|
| Table | `customer_memories` |
| Service | `CustomerMemoryService` |
| Fields | `memory_key`, `memory_value`, `category`, `confidence`, `source` |
| Prompt | `getForPrompt(companyId, phone)` — limited by `customer_memory_limit` (20) |
| Extraction | `ExtractCustomerMemoriesJob` → `CustomerMemoryExtractionService` (delayed after chat if `learn_from_conversations`) |

**Tests:** `test_customer_memory_upsert_and_retrieve`, `test_memory_extraction_stores_customer_facts`

### Agent reflections

| Piece | Detail |
|-------|--------|
| Table | `agent_reflections` |
| Service | `AgentMemoryService` |
| Use | Lightweight `reflectOnTurn` after successful replies; prompt injection of recent reflections |

---

## Chief Agent loop (orchestrator)

**Class:** `App\Services\Agent\CommerceAgentOrchestrator`

**Entry:** `CommerceAgentReplyService::generate()` → `orchestrator->run(...)`

### Return shape

```php
[
  'reply' => ?string,
  'route' => string,      // agent_cognitive*
  'handoff' => bool,
  'order_flow_reply' => ?string,
]
```

### Loop limits (`config/agent.php`)

| Key | Default | Env |
|-----|---------|-----|
| `max_loop_iterations` | 8 | `AGENT_MAX_LOOP_ITERATIONS` |
| `max_tool_calls_per_turn` | 12 | `AGENT_MAX_TOOL_CALLS` |
| `conversation_history_limit` | 16 | `AGENT_HISTORY_LIMIT` |

### LLM transport

`AgentChatService::completeWithTools()` — OpenAI-compatible tool calling; company-scoped rate limit.

### Reply routes

| Route | Meaning |
|-------|---------|
| `agent_cognitive` | Normal guarded reply |
| `agent_cognitive_order` | Order-flow reply from tool |
| `agent_cognitive_handoff` | Human handoff message |
| `agent_cognitive_failed` | No reply produced |

Higher layers inject cognitive pipeline, world model, trust logging, etc. into this same class — see [AI_COGNITIVE_OS.md](AI_COGNITIVE_OS.md) and [AI_ABI_PLATFORM.md](AI_ABI_PLATFORM.md).

---

## Proactive AI

### Abandoned cart

- Job: `ProcessAgentProactiveEventsJob` (hourly)
- Config: `proactive.abandoned_cart_hours` (24), `max_outreach_per_run` (15)
- **Event bus:** `CommerceEventDetector` detects `low_stock`, `sales_drop`, `delivery_delay`, `customer_birthday` → `commerce_agent_events`; `CommerceEventHandler` sends customer outreach for delivery delays and birthdays
- Column: `orders.agent_proactive_follow_up_at` prevents duplicate outreach
- Message: `AgentProactiveMessageService::abandonedCartMessage()`

**Test:** `test_proactive_job_sends_abandoned_cart_follow_up`

### Payment thank-you

- Hook: `OrderPaymentService` when proactive enabled
- Message: `AgentProactiveMessageService::paymentReceivedMessage()`

**Test:** `test_payment_confirmation_uses_proactive_message_when_enabled`

---

## Migrations (Layer 1)

| Migration | Contents |
|-----------|----------|
| `2026_07_11_120000_create_agent_os_tables` | enable flag, customer_memories, tool_invocations, reflections |
| `2026_07_11_130000_add_agent_goals_and_proactive_to_company_settings` | goals JSON, proactive flag |
| `2026_07_11_130001_add_agent_proactive_follow_up_to_orders` | follow-up timestamp |
| `2026_07_11_170000_create_commerce_specialist_and_graph_tables` | commerce_agent_runs, product_relationships, commerce_agent_events |

---

## Phase 3 — Multi-agent specialists (Growth Engine pattern)

| Piece | Detail |
|-------|--------|
| Orchestrator | `CommerceSpecialistOrchestrator` |
| Specialists | `SalesSpecialistService`, `SupportSpecialistService`, `InventorySpecialistService` |
| Per-turn consult | `consultForTurn()` — feeds `InternalDebateService` (rule-based default; optional LLM via `AGENT_SPECIALISTS_USE_LLM`) |
| Background pipeline | `dispatchBackgroundPipeline()` → `RunCommerceSpecialistJob` → `commerce_agent_runs` |
| API | `GET /api/company/commerce-specialists/runs`, `POST /api/company/commerce-specialists/run` |

**Tests:** `CommerceAgentPhase3Test` (12 tests)

---

## Phase 4 — Vision, unified brain, external tool bus

| Piece | Detail |
|-------|--------|
| Vision | `VisionPipelineService` — WhatsApp images → product/warranty recognition |
| Brain | `UnifiedCompanyBrainService` — Commerce + Growth in one prompt block |
| Owner analytics | `OwnerAnalyticsAgentService` — "Why are sales down?" with evidence |
| External tools | M-Pesa, shipping quote, calendar, marketing performance |
| APIs | `/api/company/company-brain`, `/api/company/owner-analytics/investigate` |

**Tests:** `CommerceAgentPhase4Test` (11 tests)  
**Doc:** [AI Phase 4 OS](AI_PHASE4_OS.md)

---

## Vector search (implemented)

Product and knowledge search use **MySQL JSON embeddings + in-app cosine similarity** via `KnowledgeChunkService` and `VectorSimilarity` — not pgvector/Pinecone. Suitable for current scale; pgvector migration is a future optimization for very large catalogs.

---

## Roadmap (Layer 1 — remaining)

1. ~~Separate specialist LLM workers~~ → **Done** (Sales/Support/Inventory + orchestrator)
2. ~~More proactive triggers~~ → **Done** (low stock, sales drop, delivery delay, birthdays)
3. ~~Vector index~~ → **Done** (JSON embeddings + cosine; pgvector at scale is future)
4. Expose DNA/twin in Settings API (currently Cognitive/Company columns only)
5. Owner alerts UI for low_stock / sales_drop events (detected but no owner notification yet)
6. CRM/ERP/shipping API connectors beyond weather + delivery status + configurable shipping API

See [AI Phase 4 OS](AI_PHASE4_OS.md) for implemented Phase 4 items.

---

## Verification

```bash
cd LARAVEL_BACKEND
php artisan migrate --force
php artisan agent:verify
php artisan test --filter=CommerceAgentTest
php artisan test --filter=CommerceAgentFlow
php artisan test --filter=WhatsApp
```

### Manual smoke

1. Dashboard → **Settings → AI** → enable **Auto-reply** and **Agent commerce mode ON**
2. Optional: **Business DNA** → Luxury brand or Friendly café preset
3. Send WhatsApp message  
4. Confirm `messages.reply_source` is `agent_cognitive*`  
5. Confirm rows in `agent_tool_invocations` when tools were used  
6. Enable proactive; age a pending order >24h; run proactive job  

---

## Code map

| Path | Role |
|------|------|
| `CommerceAgentOrchestrator.php` | Chief loop |
| `CommerceAgentReplyService.php` | Feature gate |
| `AgentToolRegistry.php` / `AgentToolRunner.php` | Tools |
| `Tools/*` | Tool implementations |
| `CustomerMemoryService.php` | Customer memory |
| `AgentMemoryService.php` | Reflections |
| `BusinessGoalService.php` | Goals |
| `AgentProactiveMessageService.php` | Proactive copy |
| `Jobs/Agent/ProcessAgentProactiveEventsJob.php` | Hourly outreach |
| `Jobs/Agent/ExtractCustomerMemoriesJob.php` | Memory extraction |
| `config/agent.php` | Limits & catalogs |
| `Jobs/ProcessIncomingWhatsAppMessage.php` | WhatsApp integration |
| `Specialists/*` | Sales/Support/Inventory workers |
| `Events/*` | Commerce event bus |

See also: [AI Company OS](AI_COMPANY_OS.md) · [AI Platform OS](AI_PLATFORM_OS.md) · [AI Cognitive OS](AI_COGNITIVE_OS.md) · [ABI Platform](AI_ABI_PLATFORM.md)
