# WhatsApp Reply Bot – Product Specification

**Single source of truth** for the AI WhatsApp Business Reply Bot & Order Management System. Use this document for implementation, proposals, and team alignment.

---

## Table of contents

1. [Project overview](#1-project-overview)
2. [Technical requirements](#2-technical-requirements)
3. [API requirements](#3-api-requirements)
4. [Core functional requirements](#4-core-functional-requirements)
5. [Non-functional requirements](#5-non-functional-requirements)
6. [Database requirements (core tables)](#6-database-requirements-core-tables)
7. [UI requirements](#7-ui-requirements)
8. [Backend module structure](#8-backend-module-structure)
9. [Deployment requirements](#9-deployment-requirements)
10. [Legal / policy requirements](#10-legal--policy-requirements)
11. [Optional advanced features](#11-optional-advanced-features)
12. [MVP requirements](#12-mvp-requirements)
13. [Part A: Database design (ERD)](#13-part-a-database-design-erd)
14. [Part B: Data flow diagrams (DFD)](#14-part-b-data-flow-diagrams-dfd)

---

## 1. Project overview

A **SaaS platform** where businesses subscribe and connect their WhatsApp number. The system then:

- Replies to customers instantly
- Answers FAQs
- Sends price list / catalog
- Collects orders
- Sends order confirmation
- Optionally requests payment (M-Pesa)
- Provides **admin dashboard** for the business owner
- Provides **super admin dashboard** for the platform owner

---

## 2. Technical requirements

### 2.1 Server / hosting

| Item | Requirement |
|------|-------------|
| Hosting | VPS / Cloud (e.g. DigitalOcean, AWS, Contabo, Hetzner) |
| CPU | Minimum 2 vCPU |
| RAM | Minimum 4GB |
| Storage | 50GB SSD |
| OS | Ubuntu 22.04 recommended |
| Web server | Nginx or Apache |
| SSL | Certificate (e.g. Let’s Encrypt) |

### 2.2 Software stack

| Component | Requirement |
|-----------|-------------|
| PHP | 8.1+ (8.2 recommended) |
| Framework | Laravel 10 or 11 |
| Database | MySQL or MariaDB |
| Cache / queue | Redis (optional but recommended) |
| Queue workers | Supervisor |
| Scheduler | Cron enabled |

---

## 3. API requirements

### 3.1 WhatsApp

**Primary:** Meta WhatsApp Cloud API.

Required:

- Meta Developer Account
- Meta Business Account
- WhatsApp Business Account (WABA)
- Phone Number ID
- Permanent Access Token
- Webhook Verification Token

### 3.2 AI

- **Provider:** OpenAI (ChatGPT integration)
- **Model:** GPT-4o-mini (cost-effective and fast)
- **API key:** Stored securely

### 3.3 Payments (optional)

- **Safaricom Daraja API (M-Pesa)**
  - Consumer Key & Secret
  - Shortcode
  - Passkey
  - Callback URL

---

## 4. Core functional requirements

### A. Authentication & roles

| Requirement | Detail |
|-------------|--------|
| Roles | Super Admin, Business Owner, Staff/Agents (optional) |
| Auth | Login, register, password reset |
| Access | Role-based access control |
| Profile | User profile settings |

### B. Business management (multi-tenant SaaS)

- Multiple businesses per platform.
- **Business registration** and **profile**: name, category (salon, restaurant, shop, clinic, etc.), location, working hours, delivery charges, social links.
- Each business manages: **products**, **FAQs**, **automated messages**.

### C. WhatsApp connection

- Store per business: `phone_number_id`, `access_token`, `verify_token`.
- Webhook URL per business or shared webhook.
- Automatic webhook verification.
- Enable/disable bot toggle.

### D. Webhook message receiver

- Receive incoming WhatsApp messages.
- Log all messages.
- Detect type: **text**, **image**, **audio**, **document**, **location**.
- Save messages in DB and attach to customer profile.

### E. Customer management

- Auto-create customers from incoming chats.
- Store: phone, name (if available), last seen, conversation status.
- Segmentation: new vs repeat customers.

### F. Auto-reply (rules-based)

- Greeting (first message).
- Away message (outside working hours).
- Keyword replies: e.g. **price**, **location**, **opening hours**, **delivery**, **payment methods**.
- Quick menu: e.g. “1. Prices”, “2. Order”, “3. Talk to human”.

### G. FAQ management

- Add FAQ question + answer.
- FAQs used as knowledge base for AI.
- Enable/disable per FAQ entry.

### H. Product / catalog management

- Fields: name, price, description, category, availability (in stock / out of stock), image (optional).
- Bot responses to: “Send catalog”, “Prices”, “Available products”.

### I. Order management

- **Customer can order by:** product name, product number, menu selection.
- **Bot collects:** items, quantities, delivery location, phone confirmation.
- System generates **order number**.
- **Order status:** pending → confirmed → dispatched → delivered / cancelled.
- Business dashboard: orders list and detail.

### J. AI chat (ChatGPT)

- Natural, human-like answers.
- AI uses: business profile, products, FAQs, working hours.
- **Rules:** never invent prices; if message contains “price” or “location” → fetch from DB; else use AI.

### K. Conversation flow / session state

- Steps: greeting → menu → product selection → quantity → location → confirmation.
- Store conversation state in DB.
- Reset when conversation is finished.

### L. Human agent / manual reply

- Toggle “human mode”.
- Agent replies manually from dashboard.
- Bot pauses when human is replying.
- Chat inbox in WhatsApp Web–style view.

### M. Notifications

- Notify business when: new customer message, new order.
- Channels: email, SMS (optional), WhatsApp internal alert (optional).

### N. Analytics & reports

- Messages per day, customer count, conversion (orders).
- Most asked FAQs, most ordered products.
- Sales reports: daily / weekly / monthly.

### O. Subscription & billing (SaaS)

- **Plans:** e.g. Basic, AI Pro, Enterprise.
- **Limits:** messages/month, products, AI tokens.
- **Payment:** M-Pesa Paybill/Till, Stripe (optional).
- Expiry and renewal; auto-disable bot if subscription expires.

### P. Audit logs & activity tracking

- Log actions: who changed prices, deleted orders, updated FAQs, etc.
- For security and debugging.

---

## 5. Non-functional requirements

### Security

- HTTPS only.
- API tokens encrypted in DB.
- Mitigate SQL injection / XSS.
- Secure webhook verification.
- Role-based permissions.
- Rate limiting (anti-spam).

### Performance

- Laravel Queue for message processing.
- Efficient log storage.
- Cache business FAQ/product data.
- Support 1000+ messages/day without downtime.

### Reliability

- Retry sending if WhatsApp API fails.
- Webhook must return 200 quickly.
- Daily DB backups.

### Scalability

- Multi-business support.
- Scalable DB design.
- Separate queue workers for AI.

---

## 6. Database requirements (core tables)

| Group | Tables |
|-------|--------|
| Main | `users`, `roles`, `permissions`, `businesses`, `business_settings` |
| WhatsApp | `whatsapp_accounts` |
| Customer & chat | `customers`, `conversations`, `messages` |
| Product & order | `products`, `categories`, `orders`, `order_items` |
| FAQ & automation | `faqs`, `auto_replies`, `bot_rules` |
| Subscription | `plans`, `subscriptions`, `payments`, `invoices` |
| Logs | `activity_logs`, `webhook_logs`, `api_request_logs` |

---

## 7. UI requirements

### Business owner dashboard

- Overview
- WhatsApp setup
- Chat inbox
- Customers
- Orders
- Products
- FAQ management
- Settings
- Subscription / billing
- Reports

### Super admin dashboard

- All businesses list
- Manage subscriptions
- Monitor usage
- System logs
- Manage plans / pricing
- Platform-wide analytics

---

## 8. Backend module structure

| Module / service | Responsibility |
|------------------|----------------|
| WhatsAppWebhookService | Receive and validate webhooks |
| WhatsAppMessageSenderService | Send messages via WhatsApp API |
| AIReplyService | AI reply generation (OpenAI) |
| FAQService | FAQ matching and replies |
| OrderService | Order creation and status |
| ConversationStateService | Session/step state |
| SubscriptionService | Plans, expiry, limits |
| NotificationService | Email/SMS/WhatsApp alerts |
| LoggingService | Audit and debug logs |

---

## 9. Deployment requirements

- Domain (e.g. `bot.afrispark.com`).
- SSL installed.
- **Cron:** `schedule:run`.
- **Queue:** Supervisor config for workers.
- Database backups.
- Logging and monitoring.

---

## 10. Legal / policy requirements (Meta)

- Privacy policy page.
- Terms & conditions page.
- Data deletion request handling.
- User consent handling.
- Message template approval for business-initiated messages.

---

## 11. Optional advanced features

- Voice note transcription (AI).
- Multilingual (e.g. English / Swahili).
- AI product recommendations.
- Customer loyalty points.
- Google Sheets integration.
- POS integration.
- Chatbot training panel (e.g. custom knowledge base PDF upload).

---

## 12. MVP requirements

Minimum set to ship and sell quickly:

| # | Feature | Status |
|---|---------|--------|
| 1 | WhatsApp webhook receive + reply | Required |
| 2 | FAQ replies | Required |
| 3 | Price list reply | Required |
| 4 | Product list | Required |
| 5 | Order capturing (simple) | Required |
| 6 | Dashboard: orders + messages | Required |
| 7 | AI for unknown questions | Required |
| 8 | Subscription expiry system | Required |

---

## 13. Part A: Database design (ERD)

### 13.1 Users & access control

#### users

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT PK | Primary key |
| name | VARCHAR | Full name |
| email | VARCHAR UNIQUE | Email |
| phone | VARCHAR | Phone |
| password | VARCHAR | Encrypted password |
| role_id | BIGINT FK | → roles.id |
| business_id | BIGINT FK NULL | → businesses.id |
| status | ENUM(active, inactive) | Account status |
| created_at, updated_at | TIMESTAMP | |

**Relations:** `users.role_id → roles.id`, `users.business_id → businesses.id`

#### roles

| Field | Type |
|-------|------|
| id | BIGINT PK |
| name | VARCHAR |

Examples: Super Admin, Business Owner, Agent.

#### permissions (optional)

| Field | Type |
|-------|------|
| id | BIGINT PK |
| name | VARCHAR |

#### role_permissions (pivot)

| Field | Type |
|-------|------|
| id | BIGINT PK |
| role_id | BIGINT FK → roles.id |
| permission_id | BIGINT FK → permissions.id |

---

### 13.2 Business management

#### businesses

| Field | Type | Description |
|-------|------|-------------|
| id | BIGINT PK | |
| name | VARCHAR | Business name |
| category | VARCHAR | e.g. salon, restaurant, shop |
| email | VARCHAR | |
| phone | VARCHAR | |
| location | TEXT | |
| description | TEXT | |
| opening_hours | TEXT | |
| delivery_info | TEXT | |
| status | ENUM(active, inactive) | |
| created_at, updated_at | TIMESTAMP | |

**Relations:** has many users, one whatsapp_accounts, many products, faqs, customers, orders.

#### business_settings

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK → businesses.id |
| greeting_message | TEXT |
| away_message | TEXT |
| fallback_message | TEXT |
| bot_enabled | BOOLEAN |
| ai_enabled | BOOLEAN |
| human_takeover | BOOLEAN |
| language | VARCHAR |
| created_at, updated_at | TIMESTAMP |

---

### 13.3 WhatsApp integration

#### whatsapp_accounts

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK → businesses.id |
| phone_number_id | VARCHAR |
| whatsapp_business_account_id | VARCHAR |
| access_token | TEXT (encrypted) |
| verify_token | VARCHAR |
| webhook_url | TEXT |
| status | ENUM(active, inactive) |
| created_at, updated_at | TIMESTAMP |

#### webhook_logs

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| payload | LONGTEXT |
| event_type | VARCHAR |
| created_at | TIMESTAMP |

---

### 13.4 Customer & chat

#### customers

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| name | VARCHAR NULL |
| phone | VARCHAR |
| last_seen | TIMESTAMP |
| created_at, updated_at | TIMESTAMP |

**Relations:** business_id → businesses; has many messages, orders; one conversation_state.

#### conversations

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| customer_id | BIGINT FK |
| current_step | VARCHAR |
| session_data | JSON |
| is_active | BOOLEAN |
| last_message_at | TIMESTAMP |
| created_at, updated_at | TIMESTAMP |

**Relations:** customer_id → customers.id, business_id → businesses.id.

#### messages

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| customer_id | BIGINT FK |
| message_id | VARCHAR |
| direction | ENUM(incoming, outgoing) |
| message_type | ENUM(text, image, audio, document, location) |
| content | LONGTEXT |
| status | ENUM(sent, failed, received) |
| created_at | TIMESTAMP |

---

### 13.5 FAQ & auto reply

#### faqs

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| question | TEXT |
| answer | TEXT |
| keywords | TEXT |
| is_active | BOOLEAN |
| created_at, updated_at | TIMESTAMP |

#### auto_replies

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| trigger_keyword | VARCHAR |
| reply_message | TEXT |
| match_type | ENUM(exact, contains, regex) |
| is_active | BOOLEAN |
| created_at, updated_at | TIMESTAMP |

---

### 13.6 Products & catalog

#### categories

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| name | VARCHAR |
| created_at | TIMESTAMP |

**Relations:** business_id → businesses; has many products.

#### products

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| category_id | BIGINT FK |
| name | VARCHAR |
| description | TEXT |
| price | DECIMAL |
| stock_status | ENUM(in_stock, out_of_stock) |
| image | VARCHAR NULL |
| is_active | BOOLEAN |
| created_at, updated_at | TIMESTAMP |

---

### 13.7 Orders

#### orders

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| customer_id | BIGINT FK |
| order_number | VARCHAR UNIQUE |
| status | ENUM(pending, confirmed, dispatched, delivered, cancelled) |
| delivery_location | TEXT |
| total_amount | DECIMAL |
| payment_status | ENUM(unpaid, paid, partial) |
| notes | TEXT |
| created_at, updated_at | TIMESTAMP |

**Relations:** business_id, customer_id; has many order_items.

#### order_items

| Field | Type |
|-------|------|
| id | BIGINT PK |
| order_id | BIGINT FK → orders.id |
| product_id | BIGINT FK NULL |
| product_name | VARCHAR |
| quantity | INT |
| unit_price | DECIMAL |
| subtotal | DECIMAL |

---

### 13.8 Payment & subscription

#### plans

| Field | Type |
|-------|------|
| id | BIGINT PK |
| name | VARCHAR |
| price | DECIMAL |
| duration_days | INT |
| message_limit | INT |
| ai_enabled | BOOLEAN |
| created_at | TIMESTAMP |

**Relation:** has many subscriptions.

#### subscriptions

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| plan_id | BIGINT FK |
| start_date | DATE |
| end_date | DATE |
| status | ENUM(active, expired, cancelled) |
| created_at | TIMESTAMP |

**Relations:** business_id → businesses, plan_id → plans.

#### payments

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| subscription_id | BIGINT FK |
| amount | DECIMAL |
| method | ENUM(mpesa, stripe, cash) |
| transaction_code | VARCHAR |
| status | ENUM(pending, paid, failed) |
| paid_at | TIMESTAMP |
| created_at | TIMESTAMP |

---

### 13.9 AI & system logs

#### ai_logs

| Field | Type |
|-------|------|
| id | BIGINT PK |
| business_id | BIGINT FK |
| customer_id | BIGINT FK |
| prompt | LONGTEXT |
| response | LONGTEXT |
| tokens_used | INT |
| created_at | TIMESTAMP |

#### activity_logs

| Field | Type |
|-------|------|
| id | BIGINT PK |
| user_id | BIGINT FK |
| business_id | BIGINT FK |
| action | VARCHAR |
| description | TEXT |
| created_at | TIMESTAMP |

---

### 13.10 ERD relationship summary

| From | To | Cardinality |
|------|-----|-------------|
| roles | users | 1 → many |
| businesses | users | 1 → many |
| businesses | whatsapp_accounts | 1 → 1 |
| businesses | business_settings | 1 → 1 |
| businesses | customers | 1 → many |
| customers | messages | 1 → many |
| customers | orders | 1 → many |
| orders | order_items | 1 → many |
| businesses | products | 1 → many |
| categories | products | 1 → many |
| businesses | faqs | 1 → many |
| businesses | subscriptions | 1 → many |
| subscriptions | payments | 1 → many |
| businesses | ai_logs | 1 → many |

---

## 14. Part B: Data flow diagrams (DFD)

### 14.1 DFD Level 0 (context)

**External entities**

1. Customer (WhatsApp user)
2. Business owner
3. Meta WhatsApp Cloud API
4. OpenAI API
5. Payment provider (M-Pesa Daraja API)
6. Super admin

**Flows**

| From | To | Flow |
|------|----|------|
| Customer | System | Message, catalog request, order |
| System | Customer | Auto reply, price list, order confirmation |
| System ↔ | Meta WhatsApp Cloud API | Webhook receive, send replies |
| System ↔ | OpenAI API | Prompt request, AI response |
| System ↔ | M-Pesa API | STK push, payment callback |
| Business owner ↔ | System | Products, FAQs, messages, orders, manual reply |
| Super admin ↔ | System | Subscriptions, plans, logs, analytics |

**Text diagram (Level 0)**

```
Customer (WhatsApp)
        |
        | Message / Order request
        v
[ AI WhatsApp Bot System ] <-----> Meta WhatsApp Cloud API
        |
        | AI query
        v
    OpenAI API
        |
        | Payment request
        v
    M-Pesa API

Business Owner <-----> [ AI WhatsApp Bot System ] <-----> Super Admin
```

---

### 14.2 DFD Level 1 (processes)

**Processes**

| ID | Process |
|----|---------|
| 1.0 | User authentication & access control |
| 2.0 | WhatsApp message handling |
| 3.0 | FAQ & auto-reply processing |
| 4.0 | AI response generation |
| 5.0 | Product & price list processing |
| 6.0 | Order management processing |
| 7.0 | Subscription & billing processing |
| 8.0 | Reporting & analytics |

**Data stores**

| ID | Store |
|----|-------|
| D1 | Users |
| D2 | Businesses |
| D3 | WhatsApp accounts |
| D4 | Customers |
| D5 | Messages |
| D6 | FAQs |
| D7 | Products |
| D8 | Orders (+ order_items) |
| D9 | Subscriptions |
| D10 | AI logs |

**Level 1 flow summary**

| Process | Input | Output | Data stores |
|---------|--------|--------|-------------|
| 1.0 Auth | Login credentials | Session, dashboard access | D1, D2 |
| 2.0 WhatsApp | Customer message (webhook) | Stored message, trigger reply flow | D4, D5 |
| 3.0 FAQ / auto-reply | Incoming message | Reply if keyword match | D6, auto_replies |
| 4.0 AI | Message + business context | AI response | D10, D2, D7, D6 |
| 5.0 Product / price | “prices”, “catalog” | Formatted product/price message | D7, categories |
| 6.0 Orders | Order request | Order record, confirmation | D8, D4 |
| 7.0 Subscription | Subscription + payment | Active subscription, invoice | D9, payments |
| 8.0 Reporting | History | Charts, reports | D5, D8, D4 |

**Text diagram (Level 1)**

```
Customer
    |
    | WhatsApp message
    v
Meta WhatsApp API
    |
    | Webhook
    v
(2.0 WhatsApp message handling) ---> D4 Customers, D5 Messages
    |
    v
(3.0 FAQ & auto-reply) ---> D6 FAQs
    |
    | if no match
    v
(4.0 AI response) <-----> OpenAI API, D10 AI logs
    |
    v
(5.0 Product / price list) ---> D7 Products
    |
    v
(6.0 Order management) ---> D8 Orders, order_items
    |
    v
Meta WhatsApp API --> Reply --> Customer

Business owner --> (1.0 Auth) --> D1 Users
Business owner --> Manage products/FAQs --> D7, D6
Business owner --> View orders --> D8
Business owner --> Subscription --> (7.0 Billing) <----> M-Pesa
Super admin --> Manage system --> D2, D9
```

**Optional sub-processes (Level 1 detail)**

- 2.1 Validate webhook token  
- 2.2 Save incoming message  
- 2.3 Detect message type  
- 4.1 Build AI prompt  
- 4.2 Fetch business context  
- 4.3 Generate AI response  
- 6.1 Create order  
- 6.2 Update order status  
- 6.3 Send order notification to business  

---

## Document info

- **Purpose:** Single source of truth for the WhatsApp Reply Bot product.
- **Use for:** Implementation, backend/frontend alignment, proposals, onboarding.
- **Related docs:** `API_INTEGRATION_CHECKLIST.md`, `LARAVEL_API.md`.
