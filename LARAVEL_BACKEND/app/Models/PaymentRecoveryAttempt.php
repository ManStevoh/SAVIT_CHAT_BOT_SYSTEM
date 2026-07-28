<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRecoveryAttempt extends Model
{
    protected $fillable = [
        'order_id',
        'company_id',
        'attempt_number',
        'hours_after_order',
        'channel',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'attempt_number' => 'integer',
        'hours_after_order' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
