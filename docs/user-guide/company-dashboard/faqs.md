---
title: FAQs
parent: Company Dashboard
nav_order: 15
---

# FAQs (Knowledge Base)

**URL:** `/dashboard/faq`

FAQs power both **direct keyword matching** and **AI context** for customer replies.

## FAQ structure

Each FAQ entry has:

| Field | Description |
|-------|-------------|
| Question | The customer question or topic |
| Answer | Exact reply text (used on high-confidence match) |
| Keywords | Comma-separated triggers (e.g. "hours, opening, time") |
| Active | Enable/disable without deleting |

## How FAQs are used

When a customer sends a message, the bot checks in order:

1. **Greeting** — first message in chat
2. **Escalation keywords** — agent, human, support → human mode
3. **Order flow** — if customer is mid-order
4. **Keyword triggers** — platform keywords (catalog, order, price)
5. **FAQ match** — fuzzy match against questions/keywords
6. **OpenAI** — if no FAQ match and AI is enabled
7. **Fallback** — generic "contact us" message

High-confidence FAQ matches (`FAQ_DIRECT_MIN_SCORE`) send the FAQ answer **directly** without calling OpenAI — faster and cheaper.

## Writing effective FAQs

| Tip | Example |
|-----|---------|
| Use natural question phrasing | "What are your opening hours?" |
| Add keyword variants | "hours, open, closing, schedule" |
| Keep answers concise | WhatsApp-friendly short paragraphs |
| Include exact prices in answers only when static | "Delivery fee is KES 200" |
| For dynamic prices | Direct customers to "catalog" keyword instead |

## Bulk import

Import FAQs from CSV — see [Import & Export](data-import-export.md).

Sample format:

```csv
question,answer,keywords,active
What are your hours?,Mon-Sat 9am-6pm,hours;opening;time,true
```

## AI + FAQs together

Even when OpenAI generates a reply, it receives your FAQs and products as context. Accurate FAQs reduce hallucinations and API costs.
