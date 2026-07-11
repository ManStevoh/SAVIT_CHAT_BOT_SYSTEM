# Honest Remaining Gaps (Post Operating System)

**Verified:** 2026-07-11 · AI Business Operating System foundation is **production-ready**.

---

## Shipped in latest batch

| Gap | Status |
|-----|--------|
| pgvector at scale | **Optional path** — `PgVectorSearchService` + migration (PostgreSQL only); JSON+cosine default on SQLite/SMB |
| DHL / Sendy connectors | **Adapter v1** — `dhl_shipping`, `sendy_logistics` in registry + sync API |
| Owner analytics UI | **`/dashboard/business-intelligence`** — investigations + question form |
| Company brain dashboard | **Same page** — brain snapshot, refresh, digest |
| Commerce integrations UI | **Agent Ops** — connector list, sync/disconnect |
| Outbound vision images | **`VisionOutboundImageService`** — sends catalog product image after vision match |
| Vector search status API | **`GET /knowledge/vector-status`** |

---

## Still roadmap (honest — not blockers)

### pgvector production enablement

Set `AI_PGVECTOR_ENABLED=true` on PostgreSQL with `vector` extension. Until then, JSON + in-memory cosine is correct for current catalog sizes.

### Deep carrier integrations

DHL/Sendy adapters test quote endpoints — full rate shopping, label printing, and tracking webhooks are separate projects.

### Deep CRM/ERP

HubSpot/Salesforce OAuth, two-way SKU mapping, inventory write-back.

### TTS voice replies

Whisper inbound is wired; outbound TTS slot seeded but not connected to WhatsApp.

---

## Verification

```bash
php artisan migrate
php artisan test --filter=CommerceAgentOperatingSystem
php artisan test --filter=CommerceAgent
php artisan test --filter=AiOrchestration
php artisan ai:verify-orchestration
