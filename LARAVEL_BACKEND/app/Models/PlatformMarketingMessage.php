<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformMarketingMessage extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_ARCHIVED = 'archived';

    public const MODE_MANUAL = 'manual';

    public const MODE_SCHEDULED = 'scheduled';

    public const MODE_RECURRING = 'recurring';

    public const MODE_TRIGGER = 'trigger';

    public const AUDIENCES = [
        'all_merchants',
        'marketing_consent',
        'no_product',
        'no_payments',
        'no_whatsapp',
        'trial',
        'paid',
    ];

    protected $fillable = [
        'name',
        'title',
        'body_html',
        'body_text',
        'cta_label',
        'cta_url',
        'channel_email',
        'channel_whatsapp',
        'channel_popup',
        'audience',
        'status',
        'send_mode',
        'scheduled_at',
        'recurring_interval',
        'trigger',
        'trigger_delay_seconds',
        'popup_once',
        'created_by',
        'last_run_at',
    ];

    protected $casts = [
        'channel_email' => 'boolean',
        'channel_whatsapp' => 'boolean',
        'channel_popup' => 'boolean',
        'popup_once' => 'boolean',
        'scheduled_at' => 'datetime',
        'last_run_at' => 'datetime',
        'trigger_delay_seconds' => 'integer',
    ];

    public function sends(): HasMany
    {
        return $this->hasMany(PlatformMarketingSend::class, 'message_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isLive(): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            return true;
        }

        return $this->status === self::STATUS_SCHEDULED
            && $this->scheduled_at
            && $this->scheduled_at->lte(now());
    }
}
