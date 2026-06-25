---
title: Roles & Access
parent: User Guide
nav_order: 3
---

# Roles & Access Control

## User roles

| Role | Who | Access |
|------|-----|--------|
| **Super Admin** | Platform operator (Essem team) | Full admin panel at `/admin` — all companies, plans, platform settings |
| **Company Owner** | Business registrant | Full company dashboard for their tenant |
| **Company Team Member** | Staff invited by owner | Company dashboard (team view; permissions per implementation) |

## Route access

| Area | URL prefix | Auth required | Extra requirement |
|------|------------|---------------|-------------------|
| Landing page | `/` | No | — |
| Auth pages | `/login`, `/register`, etc. | No | — |
| Company dashboard | `/dashboard/*` | Yes (Sanctum token) | Active subscription (except subscription/checkout routes) |
| Super admin | `/admin/*` | Yes | Admin role |

## Subscription gate

Most company API routes and dashboard features require an **active subscription**. If subscription expires:

- WhatsApp customers receive a "service unavailable" message instead of bot replies
- Dashboard shows subscription renewal prompts
- Subscription and checkout pages remain accessible so you can renew

## Impersonation (super admin only)

Super admins can impersonate a company or user for support:

1. **Admin → Companies** or **Admin → Users**
2. Click **Impersonate**
3. You are logged in as that user in the company dashboard
4. Log out to return to admin session

Use impersonation only for legitimate support — actions are attributed to the impersonated user.

## Email verification

New accounts must verify email before certain actions. Unverified users see prompts to resend verification.

## Session & security

- Login returns a **Bearer token** stored in browser localStorage
- Logout clears the token server-side and locally
- Password reset uses time-limited signed tokens via email
