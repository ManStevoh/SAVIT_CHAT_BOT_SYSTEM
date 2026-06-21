# Bot Flow Improvements

Suggested improvements to the WhatsApp bot flow, grouped by impact and effort.

---

## Still to do (checklist)

Use this list to see what is **not yet implemented**. Tick as you complete.

### P0 – Reliability (do first)

- [x] **Webhook always return 200** – Wrap `processValue()` (or the whole message loop) in `try/catch` in `receive()`; on exception log and return 200 so Meta doesn’t retry and duplicate.
- [x] **Retry failed WhatsApp sends** – On `ProcessIncomingWhatsAppMessage` set `$tries = 3` and `$backoff = [10, 60, 300]` (seconds); let the job retry on failure.
- [x] **Idempotent reply job** – Before generating/sending reply in the job, check that no bot message was already created for this incoming message (e.g. by `whatsapp_message_id` + chat_id), or use `ShouldBeUnique` with a unique key so the same webhook doesn’t run the job twice.
- [x] **Agent reply: reflect send result** – In `ChatMessageController::store`, after `sendText()` update the message `status` to `failed` if send failed, and return e.g. `{ success, message, whatsappSent?: boolean, whatsappError?: string }` so the UI can show failure.

### P1 – UX & spec

- [x] **Human takeover (bot pause)** – Add `agent_handling_at` (nullable timestamp) on `chats`. When agent sends a message, set it to `now()`. In `ProcessIncomingWhatsAppMessage`, if `agent_handling_at` is within last 30 minutes, skip auto-reply. Optionally add “Hand back to bot” to clear it.
- [x] **Subscription check** – In `ProcessIncomingWhatsAppMessage`, if company has no active subscription (e.g. no subscription with `status = active` and `end_date >= today`), skip auto-reply (and optionally send one “service unavailable” reply).
- [x] **Company fallback message** – Add `fallback_message` column to `company_settings`; in `AIReplyService::fallbackReply()` use it when set. Expose in company settings API and dashboard.
- [x] **Away message (working hours)** – Add company-level working hours (e.g. `business_hours` JSON or `open_time`/`close_time` + timezone). In reply flow, if outside hours send configurable away message and skip normal reply (or still save message).
- [x] **Escalate to human** – If customer message contains “agent”, “human”, “representative”, “talk to someone”, skip auto-reply and optionally trigger notification to company.

### P2 – Optional (better conversations)

- [x] **OpenAI with conversation history** – Pass last N messages (customer + bot) into the OpenAI request for context.
- [x] **Conversation state / order flow** – Store step and order draft per chat; implement “product → quantity → address → confirm” and create order on confirm.
- [x] **Better FAQ matching** – Improve beyond substring (e.g. word tokens or optional embeddings).

### P3 – Polish

- [x] **Meta status updates** – In webhook, handle `statuses` in payload and update `messages.status` (sent/delivered/read) when you have the matching message by `whatsapp_message_id`.
- [x] **New message notification** – When a new customer message is saved and bot didn’t reply (e.g. human takeover), send email to company if `notifications_enabled`.
- [x] **New order notification** – When an order is created, send email to company (e.g. using existing `MailService`).
- [x] **Update customer name** – In webhook, when `contacts[].profile.name` is present, update `chats.customer_name` for the existing chat.
- [x] **Cache platform settings** – Cache `PlatformSetting::first()` for 1–5 minutes in webhook and AI reply to reduce DB load.
- [x] **Rate limiting** – Throttle webhook by `phone_number_id` or IP.

### Quick wins (no new tables)

- [x] **Quick menu in greeting** – Append “Reply with: 1. Prices 2. Order 3. Talk to agent” to default or company greeting text.
- [x] **Keywords: location / hours / delivery** – If company has address or settings, reply to “location”, “hours”, “delivery” with that info.
- [x] **Handle reactions** – In webhook, if `type === 'reaction'` return 200 (optionally log); avoid treating as unhandled.

---

## 1. Reliability & performance

| Improvement | Why | Effort |
|-------------|-----|--------|
| **Retry failed WhatsApp sends** | If Meta API is temporarily down, the reply is lost. Use `$this->tries = 3` and `backoff()` on `ProcessIncomingWhatsAppMessage`, and optionally retry inside the job with delay. | Low |
| **Return 200 even when processing fails** | If `processValue()` or DB throws, the webhook returns 500 and Meta retries. We should `try/catch` in `receive()`, log the error, and return 200 so we don’t get duplicate webhooks; fix the cause and reprocess from logs if needed. | Low |
| **Idempotent job dispatch** | Dedupe by `whatsapp_message_id` (or chat_id + wa_id + timestamp) so the same incoming message doesn’t trigger two jobs (e.g. after a retry). | Low |
| **Agent reply: reflect WhatsApp send result** | When an agent sends from the dashboard, we call WhatsApp but don’t update the message `status` to `failed` if send fails, and don’t show the error to the user. Save send result and optionally return it in the API. | Low |
| **Cache platform settings** | `PlatformSetting::first()` is called on every webhook and in AI reply. Cache for 1–5 minutes to reduce DB load. | Low |

---

## 2. Human takeover (bot pause)

**Spec:** “Toggle human mode; bot pauses when human is replying.”

| Improvement | Why | Effort |
|-------------|-----|--------|
| **Per-chat “agent replying” or “bot paused”** | When an agent is in the chat or has recently replied, the bot should not auto-reply so the conversation stays human-led. | Medium |
| **Implementation** | Add e.g. `agent_handling_at` (nullable timestamp) or `bot_paused` on `chats`. When agent sends a message, set it; when customer replies, if within last N minutes (e.g. 30), skip auto-reply. Optionally: “Take over” button in UI that sets the flag; “Hand back to bot” clears it. | Medium |

---

## 3. Conversation state & order flow

**Spec:** “Steps: greeting → menu → product selection → quantity → location → confirmation.”

| Improvement | Why | Effort |
|-------------|-----|--------|
| **Conversation state per chat** | Store current step and collected data (e.g. `order_draft`: items, quantities, delivery address). Enables “I want 2 x Product A” → “What’s your delivery address?” → “Confirm order?”. | High |
| **Order creation from chat** | When customer confirms in the flow, create an `Order` and `order_items` from the draft and send confirmation with order number. | High |
| **Reset state** | When order is confirmed or user says “cancel” / “start over”, clear the state. | Medium |

---

## 4. Smarter replies & AI

| Improvement | Why | Effort |
|-------------|-----|--------|
| **OpenAI with conversation history** | Send last N messages (customer + bot) so the AI has context (e.g. “What’s the price?” after “I want Product A”). | Medium |
| **Better FAQ matching** | Current match is substring/keyword. Add optional semantic similarity (e.g. embeddings) or at least word-based matching so “when do you deliver?” matches “What are delivery hours?”. | Medium |
| **Escalate to human** | If user says “agent”, “human”, “representative”, or AI returns a low-confidence marker, skip auto-reply and optionally notify the company (e.g. “Customer requested human”). | Low |
| **Away message (working hours)** | **Spec:** “Away message (outside working hours)”. Add company `working_hours` (or open/close times) and timezone; if message is outside hours, reply with configurable away message and optionally still save the message. | Medium |
| **Company fallback message** | Add `fallback_message` to `company_settings` so each company can customize “Thanks for your message…” instead of a hardcoded string. | Low |

---

## 5. Webhook & Meta

| Improvement | Why | Effort |
|-------------|-----|--------|
| **Handle status updates** | Meta sends `message.sent`, `message.delivered`, `message.read`. Handle these in the webhook and update `messages.status` (e.g. `sent` → `delivered` → `read`) for better UX in the dashboard. | Low |
| **Handle reactions** | Meta can send `reaction` type. Either ignore and return 200, or store “customer reacted to message X” for analytics. | Low |
| **Rate limiting** | Throttle webhook by `phone_number_id` or IP to reduce abuse and accidental loops. | Low |

---

## 6. Notifications & business alerts

**Spec:** “Notify business when: new customer message, new order.”

| Improvement | Why | Effort |
|-------------|-----|--------|
| **New message notification** | When a new customer message is saved and (optionally) when bot didn’t reply (e.g. human takeover or after hours), send email to company (using existing MailService + company email) if `notifications_enabled`. | Medium |
| **New order notification** | When an order is created (from dashboard or future order flow), send email to company. | Low |

---

## 7. Subscription & limits

**Spec:** “Auto-disable bot if subscription expires”; “Limits: messages/month”.

| Improvement | Why | Effort |
|-------------|-----|--------|
| **Disable bot when subscription expired** | In `ProcessIncomingWhatsAppMessage`, check company’s active subscription; if none or expired, skip auto-reply (and optionally send “Service temporarily unavailable” or a single reply). | Low |
| **Message / usage limits** | Per plan, enforce “messages per month” or “AI requests per month”; when over limit, skip AI or all auto-reply and optionally notify company. | Medium |

---

## 8. Quick wins (minimal code)

- **Quick menu in greeting:** Add optional “Reply with: 1. Prices 2. Order 3. Talk to agent” to default or company greeting.
- **Keyword “location” / “hours” / “delivery”:** Return company address, working hours, delivery info from company or company_settings if available.
- **Update customer name:** When webhook sends `contacts[].profile.name`, update `chats.customer_name` for existing chat so the dashboard shows the correct name.
- **Log webhook payload (debug):** In development or when a flag is set, log a short hash or non-PII part of the payload to help debug Meta issues.

---

## Priority overview

| Priority | Focus | Examples |
|----------|--------|----------|
| **P0** | Don’t lose messages, don’t break Meta | Webhook always 200 on our errors, retry failed sends, idempotent job |
| **P1** | UX and spec alignment | Human takeover, away message, fallback message, subscription check |
| **P2** | Better conversations | Order flow, conversation history in AI, better FAQ match |
| **P3** | Polish | Status updates, notifications, rate limiting, usage limits |

Implementing P0 and selected P1 items will make the bot more reliable and closer to the intended product behavior.
