# Platform default timezone

Configured in **Admin → Settings → General → Default Timezone**. The dropdown lists all IANA timezones (e.g. `America/New_York`, `Europe/London`). You can pick any timezone in the world.

## What happens when you set it?

- The value is stored in **platform settings** and used whenever the system needs to show a date/time and no other timezone is specified (e.g. per-company).

## Where it is used

| Area | How it’s used |
|------|----------------|
| **Test email (Admin Settings)** | The “Send test email” email includes a “Sent at …” line formatted in the platform timezone. |
| **Other system emails** | Dates in subscription/invoice/reminder emails can be formatted in this timezone when the backend uses `MailService::formatInPlatformTimezone()`. Callers can pass a date and it will be shown in the platform default. |
| **Exports / reports** | Any admin export or report that formats timestamps (e.g. CSV/JSON with `created_at`) can use this timezone so exported times match the chosen default. |
| **Future: company default** | When a company does not set its own timezone (e.g. in company settings or working hours), the platform default can be used as fallback. |
| **Future: working hours / away message** | If you add “business hours” or “away message” by time of day, the platform (or company) timezone will define “current time” for that logic. |

## Technical note

- **Backend:** `App\Services\MailService::platformTimezone()` returns the configured value (or `config('app.timezone')` / `UTC`). `MailService::formatInPlatformTimezone($date, $format)` formats a date in that timezone.
- **Laravel `config('app.timezone')`** is separate: it’s the app server’s default (often `UTC`). The **platform default timezone** in the database overrides that for user-facing date formatting (emails, exports, future features) when the code uses the MailService helper.
