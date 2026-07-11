# ABI Levels 1–20 — Honest Status Summary

**Verified:** 2026-07-11 · `php artisan test --filter=CommerceAgent` · `POST /api/company/intelligence/reason`

This is the executive summary. Full evidence per level: [AI_ABI_PLATFORM.md Part G](AI_ABI_PLATFORM.md).

---

## Headline

| | Count |
|---|------|
| **Implemented** (production-ready surface) | **6** levels |
| **Partial** (real foundation, missing depth/UI/product) | **13** levels |
| **Roadmap** (not built) | **1** level |

**SAVIT is not “full ABI.”** It is a **production AI operating system** with **decision-intelligence APIs** and **honest partial foundations** across Levels 1–20.

---

## At a glance

| Level | Name | Status | Strongest evidence today |
|-------|------|--------|--------------------------|
| 1 | Understanding (ontology) | **Partial** | World model, causal analysis, health score |
| 2 | Curiosity | **Partial** | Investigation **case files** + background thinking |
| 3 | Hypothesis generation | **Partial** | Case steps, reasoning traces, `POST /reason` hypotheses |
| 4 | Bayesian thinking | **Partial** | **Buy/churn/refund probability scores** on reason |
| 5 | Counterfactual reasoning | **Partial** | **SimulationService** + API + tests ✅ |
| 6 | Long-term planning | **Partial** | Executive plans API; no multi-year capital model |
| 7 | Organizational intelligence | **Partial** | Digital workforce + **role/amount approval policies** |
| 8 | Trust modeling | **Partial** | Agent trust logs; no supplier/courier scores |
| 9 | Decision economics | **Partial** | Economic reasoning, opportunities, simulation |
| 10 | Resource allocation | **Roadmap** | No capital optimizer |
| 11 | Organizational memory | **Partial** | Org + strategic memories, operating guides |
| 12 | Multi-business intelligence | **Partial** | Platform patterns (#35); no group/ecosystem AI |
| 13 | Autonomous departments | **Implemented** | Sales/Support/Inventory specialists + debate |
| 14 | Digital board | **Partial** | Debate + brief + simulate; no board sessions |
| 15 | Adaptive personalities | **Implemented** | Business DNA in Settings + cognitive pipeline |
| 16 | Knowledge evolution | **Implemented** | LLM reflection → operating guides |
| 17 | Ethical guardrails | **Partial** | Governance, approvals, confidence escalate |
| 18 | Digital business genome | **Partial** | DNA + digital_twin JSON; no full genome UI |
| 19 | Intelligence API | **Implemented** | **`POST /api/company/intelligence/reason`** |
| 20 | Intelligence loop | **Partial** | **Outcome tracking API** + domain event outbox |

---

## Strong today (ship with confidence)

| Capability | Where |
|------------|--------|
| Perception, debate, confidence, critique | `CognitivePipelineService`, orchestrator |
| World model, opportunities, health | `BusinessWorldModelService`, `OpportunityDetectionService` |
| Business simulation | `SimulationService`, `POST /cognitive-ai/simulate`, tests |
| Executive plans | `ExecutivePlanningService`, `POST /cognitive-ai/plans` |
| Trust logs + governance | `AgentTrustService`, `GovernanceService` |
| Anonymized platform patterns | `CrossBusinessLearningService`, `MetaLearningService` |
| **Unified decision API** | `IntelligenceReasoningService`, `POST /intelligence/reason` |
| **Investigation cases** | `investigation_cases`, `GET /intelligence/cases` |
| **Outcome tracking** | `intelligence_outcomes`, `POST /intelligence/outcomes` |
| **Audit center (v1)** | `audit_events` + `AuditService` (respects `audit_logging_enabled`) |
| **Event bus (v1)** | `domain_events` + `DispatchDomainEventsJob` |
| **Approval routing** | `CompanyPolicyService` + `company_policy_rules` |
| **Owner dashboards** | `/dashboard/executive`, `/dashboard/cognitive` |
| Agent commerce + DNA | Settings → AI → Agent commerce ON + Business DNA |

---

## Still roadmap (do not oversell)

| Gap | Levels | Notes |
|-----|--------|-------|
| Full business ontology expansion | 1 | |
| Multi-source investigation depth | 2–3 | Case files shipped — deepen external probes |
| ML-calibrated Bayesian models | 4 | Heuristic scores shipped — train on outcomes |
| Multi-year capital / hiring / branch models | 6, 10 | |
| Full org chart approval UI | 7 | Policy engine v1 shipped |
| Entity trust (suppliers, couriers) | 8 | |
| Multi-business ecosystem AI | 12 | |
| Autonomous board sessions | 13–14 | |
| Per-role specialist personalities | 15 | |
| Policy engine DSL + legal packs | 17 | |
| Full genome versioning | 18 | |
| Unified loop KPI controller | 20 | Outcome API shipped |
| Subscription / billing / notification engines | Platform | Phase 2 enterprise roadmap |

---

## Decision intelligence (north star)

**Product thesis:** Expose **business reasoning**, not only chat.

**Today’s path:**

```
Owner goal
  → POST /api/company/intelligence/reason
  → evidence (orders, growth, health, brain)
  → hypotheses (causal + investigation)
  → scenarios (simulation when relevant)
  → plan breakdown (when relevant)
  → executive decisions + recommended actions
  → investigation persisted (owner_analytics_investigations)
  → case file opened (investigation_cases)
  → outcome rows seeded (intelligence_outcomes)
  → probability scores (business_probability_scores)
```

**Also available:**

- `GET /api/company/intelligence/cases` — investigation case files
- `POST /api/company/intelligence/outcomes` — record recommendation outcomes

- `GET /api/company/executive-ai/dashboard` — morning decisions, approvals
- `GET /api/company/cognitive-ai/dashboard` — workforce, forecast, causal
- `POST /api/company/cognitive-ai/simulate` — scenario-only
- `POST /api/company/cognitive-ai/plans` — persist executive plan

---

## Enable checklist (owners)

1. **Settings → AI → Auto-reply ON**
2. **Settings → AI → Agent commerce mode ON**
3. Optional: **Business DNA** (Luxury brand / Friendly café / Custom)
4. Open **Cognitive AI** → ask a business question → **Reason**
