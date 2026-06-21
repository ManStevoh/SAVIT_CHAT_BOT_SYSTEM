---
title: Chats
parent: Company Dashboard
nav_order: 11
---

# Chats (Inbox)

**URL:** `/dashboard/chats`

The Chats section is your WhatsApp-style inbox for all customer conversations.

## Chat list

Each chat shows:

- Customer phone number (and name if available from WhatsApp profile)
- Last message preview
- Timestamp
- Unread indicator
- Status: bot handling vs human agent active

Click a chat to open the conversation thread.

## Message thread

| Feature | Description |
|---------|-------------|
| **Message history** | All inbound (customer) and outbound (bot/agent) messages |
| **Send reply** | Type and send manual messages — delivered to customer's WhatsApp |
| **Attachments** | View images/documents sent by customer |
| **Attribution badge** | If chat originated from Growth Engine link, shows linked post |

## Bot vs human mode

### When the bot handles messages

By default, incoming WhatsApp messages are processed automatically:

1. Greeting on first message
2. FAQ / keyword / AI reply
3. Order flow if customer wants to order

### When you take over (human mode)

When you send a manual reply from the dashboard, the bot **pauses** for that chat. Your messages go directly to WhatsApp.

Customer escalation keywords (`agent`, `human`, `support`) also trigger human mode — the bot stops replying and notifies your team.

### Hand back to bot

When you're done assisting manually:

1. Open the chat
2. Click **Hand back to bot**
3. Auto-replies resume for new customer messages

## Real-time updates

The inbox refreshes via SWR polling. New messages appear when customers reply on WhatsApp or when the bot sends responses.

## Tips

- Respond quickly when escalated — customers expect human help
- Use hand-back when the issue is resolved so the bot handles routine queries again
- Check attribution info to see which social post drove the conversation (Growth Engine)
