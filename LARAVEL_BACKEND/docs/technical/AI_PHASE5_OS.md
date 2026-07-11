# AI Phase 5 — Business Consciousness Layer (Executive UI & Execution)

Phase 5 closes the loop between **AI recommendations** and **owner action**: executive dashboard, voice commands, approval execution, cross-business learning, and A/B promotion experiments.

## Delivered capabilities

| Vision item | Implementation |
|-------------|----------------|
| **#29 Human approval workflows** | `AgentApprovalService::approve/reject`, `AgentApprovalExecutionService`, POST `/api/company/executive-ai/approvals/{id}/approve\|reject` |
| **#34 Voice-first owner commands** | `VoiceTranscriptionService` (Whisper), `OwnerVoiceCommandService`, wired in `ProcessIncomingWhatsAppMessage` before customer agent |
| **#35 Cross-business learning** | `CrossBusinessLearningService` + daily `CrossBusinessLearningJob` → `MetaLearningService::recordPattern()` |
| **#22 Autonomous experiments** | `CommerceExperimentService`, `commerce_experiments` tables, abandoned-cart A/B assignment, conversion on `OrderPaymentService::markOrderPaid` |
| **Executive dashboard UI** | `/dashboard/executive` — brief, approvals, opportunities, experiments |

## High-risk tools (20 total)

Two new tools require owner approval before execution:

- `send_whatsapp_campaign` — dispatches via `WhatsAppCampaignDispatchService` after approve
- `issue_order_refund` — sets `payment_status=refunded` after approve

Configured in `config/agent.php` under `platform.tool_risk_levels`.

## Owner voice commands (WhatsApp)

When the sender phone matches a `company_owner` user:

1. Audio messages are transcribed (`message_type=audio` from webhook).
2. `OwnerVoiceCommandService` parses intent:
   - Analytics: "why are sales down?"
   - Refund: "refund order ORD-123"
   - Campaign: "send campaign 5"
   - Brief pointer: "morning brief"

Draft actions queue `agent_action_requests`; owner approves in dashboard or continues via WhatsApp.

## A/B promotion experiments

1. Owner creates experiment in Executive AI → two message variants.
2. `ProcessAgentProactiveEventsJob` assigns variants on abandoned-cart outreach.
3. Assignment cached per order (`exp_assign:order:{id}`).
4. Payment marks conversion on the assigned variant.
5. `EvaluateCommerceExperimentsJob` (daily) or manual "Evaluate winner" picks highest conversion rate.

## Scheduled jobs

| Job | Schedule |
|-----|----------|
| `CrossBusinessLearningJob` | Daily 04:30 |
| `EvaluateCommerceExperimentsJob` | Daily 05:30 |

## API endpoints

```
GET  /api/company/executive-ai/dashboard
GET  /api/company/executive-ai/opportunities
GET  /api/company/executive-ai/approvals
POST /api/company/executive-ai/approvals/{id}/approve
POST /api/company/executive-ai/approvals/{id}/reject
GET  /api/company/commerce-brief
GET  /api/company/commerce-experiments
POST /api/company/commerce-experiments
POST /api/company/commerce-experiments/{id}/evaluate
```

## Verification

```bash
php artisan migrate
php artisan agent:verify   # expects 20 tools, Phase 5 schema
php artisan test --filter=CommerceAgent
```

## Config (`config/agent.php`)

```env
AGENT_VOICE_ENABLED=true
AGENT_WHISPER_MODEL=whisper-1
AGENT_CROSS_BUSINESS_LEARNING=true
AGENT_EXPERIMENTS_ENABLED=true
```

## Related docs

- [SAVIT_DIGITAL_NERVOUS_SYSTEM.md](./SAVIT_DIGITAL_NERVOUS_SYSTEM.md) — full vision map
- [AI_PHASE4_OS.md](./AI_PHASE4_OS.md) — vision, brain, owner analytics
- [AI_ABI_PLATFORM.md](./AI_ABI_PLATFORM.md) — platform ABI
