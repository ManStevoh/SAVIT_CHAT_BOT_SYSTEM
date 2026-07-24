<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionReminderLog extends Model
{
    protected $fillable = [
        'subscription_id',
        'company_id',
        'days_before',
        'target_end_date',
        'channel',
        'sent_at',
    ];

    protected $casts = [
        'target_end_date' => 'date',
        'sent_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
