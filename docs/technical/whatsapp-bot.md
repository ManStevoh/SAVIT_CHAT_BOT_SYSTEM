---
title: WhatsApp Bot
parent: Technical Documentation
nav_order: 7
---

# WhatsApp Bot Pipeline

End-to-end technical flow for receiving and replying to WhatsApp messages.

## Webhook endpoints

| Method | Path | Handler |
|--------|------|---------|
| GET | `/api/whatsapp/webhook` | `WhatsAppWebhookController@verify` |
| POST | `/api/whatsapp/webhook` | `WhatsAppWebhookController@receive` |

### GET verification (Meta setup)

Meta sends:

```
?hub.mode=subscribe&hub.verify_token=YOUR_TOKEN&hub.challenge=CHALLENGE
```

Backend compares `hub.verify_token` to platform setting and returns `hub.challenge` as plain text.

### POST incoming message

1. Verify `X-Hub-Signature-256` using Meta App Secret (if configured)
2. Parse JSON payload
3. Extract `phone_number_id`, sender, message type, body
4. Lookup company: `WhatsAppAccount::where('phone_number_id', $id)`
5. Deduplicate by `whatsapp_message_id`
6. Store inbound `Message`
7. Dispatch `ProcessIncomingWhatsAppMessage` job

## Job pipeline: ProcessIncomingWhatsAppMessage

```
┌─────────────────────────────────────────────────────────┐
│ ProcessIncomingWhatsAppMessage                          │
├─────────────────────────────────────────────────────────┤
│ 1. Load company settings                                │
│ 2. Check subscription active → else unavailable msg     │
│ 3. Check auto_reply_enabled → else exit                 │
│ 4. Check agent_active on chat → else exit               │
│ 5. Check escalation keywords → notify, exit             │
│ 6. Check working hours → away message if closed         │
│ 7. Greeting if first message in chat                    │
│ 8. Order flow if conversation state active              │
│ 9. Keyword triggers (catalog, order, price, etc.)       │
│ 10. FAQ match (FaqMatchingService)                      │
│     → direct reply if score >= FAQ_DIRECT_MIN_SCORE     │
│ 11. OpenAI reply (AIReplyService) if key configured     │
│ 12. Fallback message                                    │
│ 13. Send via WhatsAppMessageSenderService               │
│ 14. Store outbound Message                              │
│ 15. Send notifications (email, in-app)                  │
└─────────────────────────────────────────────────────────┘
```

## Services involved

| Service | File area | Role |
|---------|-----------|------|
| `FaqMatchingService` | `Services/Conversation/` | Fuzzy match questions/keywords |
| `CustomerMessageClassifier` | `Services/Conversation/` | Intent classification |
| `AIReplyService` | `Services/` | OpenAI completion |
| `AIPromptBuilder` | `Services/AI/` | System prompt with products/FAQs |
| `OrderFlowService` | `Services/` | Order state machine |
| `OrderPaymentService` | `Services/` | Payment step after confirm |
| `WhatsAppMessageSenderService` | `Services/` | Meta Graph API send |
| `ConversationLearningService` | `Services/` | Log samples for improvement |
| `PlanLimitService` | `Services/` | Enforce message quotas |

## Configuration hierarchy

| Config | Source | Scope |
|--------|--------|-------|
| Webhook verify token | platform_settings | Platform |
| Meta App Secret | platform_settings | Platform |
| OpenAI key/model | platform_settings | Platform |
| auto_reply_enabled | company_settings | Company |
| greeting, tone, away | company_settings | Company |
| FAQs, products | DB tables | Company |
| phone_number_id, token | whatsapp_accounts | Company |

Env fallback: `WHATSAPP_WEBHOOK_VERIFY_TOKEN`, `OPENAI_API_KEY`, etc.

## Conversation config (`config/conversation.php`)

| Key | Default | Description |
|-----|---------|-------------|
| `CONVERSATION_LOG_ROUTING` | env | Log routing decisions |
| `FAQ_DIRECT_MIN_SCORE` | env | Min score for direct FAQ reply |

## Sending messages

`WhatsAppMessageSenderService` calls Meta Graph API:

```
POST https://graph.facebook.com/v21.0/{phone_number_id}/messages
Authorization: Bearer {company_access_token}
```

Supports text, and attachment types as implemented.

## Agent manual replies

`POST /api/company/chats/{id}/messages`:

1. Stores outbound message
2. Sets `agent_active = true` on chat
3. Sends to WhatsApp via same sender service

## Hand back to bot

`POST /api/company/chats/{id}/hand-back`:

- Sets `agent_active = false`
- Bot resumes on next customer message

## Meta Embedded Signup

Optional self-service connect:

1. `GET /api/company/whatsapp/embedded/config` — app ID, config ID
2. Frontend opens Meta popup
3. `POST /api/company/whatsapp/embedded/complete` — stores tokens

See [WHATSAPP_EMBEDDED_SIGNUP.md (legacy)](../WHATSAPP_EMBEDDED_SIGNUP.md).

## Queue requirement

**Critical:** With `QUEUE_CONNECTION=database`, webhook returns 200 before job runs. Without `php artisan queue:work`, customers get no replies.

Alternative for small deployments: `QUEUE_CONNECTION=sync` (runs inline, slower webhook response).

## Logging & debugging

| Log message | Cause |
|-------------|-------|
| `unknown phone_number_id` | Company not connected or wrong ID |
| `signature verification failed` | App secret mismatch |
| `duplicate message` | Meta retry; safely ignored |
| `subscription inactive` | Company needs renewal |

Enable `CONVERSATION_LOG_ROUTING=true` for detailed routing logs.

## Message types handled

| Type | Inbound | Bot reply |
|------|---------|-----------|
| text | Yes | Full pipeline |
| image | Stored | May ask for text description |
| document | Stored | Agent review |
| location | Stored | Used in order flow |
| audio | Stored | Fallback prompt |

## Plan limits

`PlanLimitService` checks monthly message count before sending bot replies. Over-limit returns upgrade message to customer.
