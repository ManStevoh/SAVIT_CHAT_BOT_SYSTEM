---
title: AI Phase 4 — Vision, Owner Analytics, Unified Brain, External Tools
parent: Home
nav_order: 11
description: Phase 4 verified reference — multimodal WhatsApp vision, owner analytics agent, Growth↔Commerce brain bridge, external tool bus.
---

# AI Phase 4 — Vision, Owner Analytics, Unified Brain, External Tools

**Verified against production code on 2026-07-11.**

| Verification | Result |
|--------------|--------|
| `php artisan test --filter=CommerceAgent` | **63 tests** (includes Phase 4) |
| `php artisan agent:verify` | **18 tools**, 34 schema checks |
| New APIs | `company-brain`, `owner-analytics/investigate` |

---

## Phase 4 capabilities

| Capability | Implementation | Status |
|------------|----------------|--------|
| Vision pipeline | `VisionPipelineService` + `AgentChatService::completeWithVision()` | Implemented |
| WhatsApp image wiring | `incomingMessageId` on job + webhook pass-through | Implemented |
| Unified company brain | `UnifiedCompanyBrainService` → `company_brain_snapshots` | Implemented |
| Growth ↔ Commerce bridge | Brain aggregates `GrowthAnalyticsService` + world model | Implemented |
| Owner analytics agent | `OwnerAnalyticsAgentService` + investigations table | Implemented |
| External tool bus | M-Pesa, shipping, calendar, marketing tools | Implemented |
| Owner event alerts | `CommerceEventHandler::handleOwnerAlerts()` → notifications | Implemented |

---

## 1. Vision pipeline

**Flow:**

```text
WhatsApp image webhook
  → Message saved (attachment_url)
  → ProcessIncomingWhatsAppMessage(incomingMessageId)
  → VisionPipelineService::analyzeMessage()
  → OpenAI vision (multimodal content)
  → message_vision_analyses row
  → Enriched prompt block → Chief Agent
```

**Table:** `message_vision_analyses`  
**Detects:** products (catalog match), warranty/receipt cards, damage  
**Config:** `agent.vision.enabled` (`AGENT_VISION_ENABLED`)

**Test:** `CommerceAgentPhase4Test::test_vision_pipeline_analyzes_image_and_matches_product`

---

## 2. Unified company brain

**Service:** `UnifiedCompanyBrainService`

Aggregates per snapshot:
- Commerce world model (orders, stock, customers)
- Growth executive summary, platform breakdown, top posts
- Revenue 7d trend, open agent events, health score

Injected into Chief system prompt via `getForPrompt()`.  
Refreshed during `BackgroundThinkingService::processCompany()`.

**API:**
- `GET /api/company/company-brain` — latest snapshot (refresh if stale)
- `POST /api/company/company-brain/refresh` — force rebuild

**Config:** `agent.brain.enabled`, `agent.brain.snapshot_max_age_minutes`

---

## 3. Owner analytics agent

**Service:** `OwnerAnalyticsAgentService`

Owner asks: *"Why are sales down?"*

1. Gathers evidence: orders (current vs prior period), growth metrics, health score, agent events, brain digest
2. Rule-based preliminary findings
3. Full LLM synthesis with cited evidence keys
4. Persists `owner_analytics_investigations`

**API:**
- `GET /api/company/owner-analytics/investigations`
- `POST /api/company/owner-analytics/investigate` — body: `{ question, period?: "7d"|"30d"|"90d" }`

**Config:** `agent.owner_analytics.enabled`, `agent.owner_analytics.use_llm`

---

## 4. External tool bus (18 tools total)

| Tool | Service | Purpose |
|------|---------|---------|
| `check_mpesa_payment` | Order + settings lookup | M-Pesa payment status by order number |
| `get_shipping_quote` | `ShippingQuoteService` | External API or heuristic quote + ETA |
| `check_calendar_availability` | `CalendarAvailabilityService` | Working hours / appointment slots |
| `get_marketing_performance` | `GrowthAnalyticsService` + brain | Growth metrics in commerce conversations |

**Config:** `agent.external.*` — shipping API URL/key, per-tool toggles

---

## 5. Owner event alerts

`CommerceEventHandler::handleOwnerAlerts()` creates `company_notifications` (type `agent`) for `low_stock` and `sales_drop` events.

Wired in hourly `ProcessAgentProactiveEventsJob`.

---

## Migrations

`2026_07_11_180000_create_phase4_vision_brain_analytics_tables.php`:
- `message_vision_analyses`
- `company_brain_snapshots`
- `owner_analytics_investigations`

---

## Verification

```bash
cd LARAVEL_BACKEND
php artisan migrate --force
php artisan test --filter=CommerceAgentPhase4
php artisan test --filter=CommerceAgent
php artisan agent:verify
```

---

## Code map

| Concern | Path |
|---------|------|
| Vision | `app/Services/Agent/Vision/VisionPipelineService.php` |
| Brain | `app/Services/Agent/Brain/UnifiedCompanyBrainService.php` |
| Owner analytics | `app/Services/Agent/Owner/OwnerAnalyticsAgentService.php` |
| External | `app/Services/Agent/External/*` |
| Tools | `app/Services/Agent/Tools/CheckMpesaPaymentTool.php`, etc. |
| APIs | `OwnerAnalyticsController`, `CompanyBrainController` |

See also: [AI ABI Platform](AI_ABI_PLATFORM.md) · [Digital Nervous System](SAVIT_DIGITAL_NERVOUS_SYSTEM.md) · [AI Agent OS](AI_AGENT_OS.md)
