---
title: Users
parent: Super Admin
nav_order: 42
---

# Super Admin: Users

**URL:** `/admin/users`

Manage all user accounts across the platform.

## User list

| Column | Description |
|--------|-------------|
| Name | User display name |
| Email | Login email |
| Role | admin / company_owner / team |
| Company | Linked tenant (if applicable) |
| Status | active / suspended |
| Verified | Email verification status |
| Last login | Recent activity |

## Actions

| Action | Description |
|--------|-------------|
| **Suspend / Activate** | Block or restore login |
| **Reset password** | Send password reset email to user |
| **Impersonate** | Log in as this user |

## Super admin accounts

Users with `admin` role access `/admin` panel. Limit admin accounts to trusted platform staff.

## Company owners

Created automatically on registration. One owner per company initially; team members added by owner.

## Password reset (admin-initiated)

1. Find user in list
2. Click **Reset password**
3. User receives email with reset link
4. User sets new password

Does not reveal current password.

## Security practices

- Disable unused admin accounts
- Use strong passwords and 2FA on admin email (email provider level)
- Audit impersonation usage via system logs

See [Monitoring](monitoring.md) for audit trail.
