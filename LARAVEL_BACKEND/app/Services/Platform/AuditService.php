<?php

namespace App\Services\Platform;

use App\Models\AuditEvent;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit trail — respects platform audit_logging_enabled toggle.
 */
final class AuditService
{
    public function isEnabled(): bool
    {
        return (bool) Cache::remember('platform_audit_logging_enabled', 120, function () {
            return (bool) (PlatformSetting::first()?->audit_logging_enabled ?? true);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $before = null,
        ?array $after = null,
        ?int $companyId = null,
        ?User $user = null,
        array $meta = [],
    ): ?AuditEvent {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! Schema::hasTable('audit_events')) {
            Log::warning('audit_events table missing; skipping audit log', [
                'action' => $action,
            ]);

            return null;
        }

        try {
            return AuditEvent::create([
                'company_id' => $companyId,
                'user_id' => $user?->id,
                'actor_type' => $user ? 'user' : 'system',
                'action' => mb_substr($action, 0, 80),
                'subject_type' => $subjectType ? mb_substr($subjectType, 0, 80) : null,
                'subject_id' => $subjectId,
                'before' => $before,
                'after' => $after,
                'ip_address' => request()?->ip(),
                'meta' => $meta !== [] ? $meta : null,
                'created_at' => now(),
            ]);
        } catch (QueryException $e) {
            Log::warning('audit_events write failed; skipping audit log', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
