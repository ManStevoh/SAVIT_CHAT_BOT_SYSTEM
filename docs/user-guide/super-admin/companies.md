---
title: Companies
parent: Super Admin
nav_order: 41
---

# Super Admin: Companies

**URL:** `/admin/companies`

Manage all tenant businesses on the platform.

## Company list

| Column | Description |
|--------|-------------|
| Name | Business name |
| Owner | Primary company owner email |
| Status | active / suspended |
| Plan | Current subscription plan |
| WhatsApp | Connected or not |
| Created | Registration date |

Search and filter by status, plan, or name.

## Company detail

Click a company to view:

- Profile (name, category, location)
- Subscription status and history
- WhatsApp connection status
- Usage stats (messages, AI tokens, Growth posts)
- Team members
- Recent activity

## Actions

| Action | Description |
|--------|-------------|
| **Edit** | Update company profile fields |
| **Suspend / Activate** | Disable or restore platform access |
| **Impersonate** | Log in as this company for support |

### Suspending a company

Suspended companies:

- Cannot access dashboard (or see suspension message)
- WhatsApp bot stops serving customers
- Data is retained

Reactivate when issue resolved.

## Company settings (admin override)

Admins can update certain company fields not editable by tenant:

- Status
- Custom plan assignment (Enterprise)
- Notes (internal)

## Related

- [Users](users.md) — manage company owner accounts
- [Subscriptions](plans-billing.md) — billing status
- [Platform Settings](platform-settings.md) — shared integrations
