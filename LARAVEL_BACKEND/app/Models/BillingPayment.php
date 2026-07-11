<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPayment extends Model
{
    protected $fillable = [
        'company_id', 'subscription_id', 'gateway', 'external_event_id', 'external_payment_id',
        'amount', 'currency', 'status', 'payment_type', 'metadata', 'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
        'paid_at' => 'datetime',
