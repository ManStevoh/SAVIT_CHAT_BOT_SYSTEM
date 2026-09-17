<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'order_id',
        'order_total',
        'commission_rate',
        'commission_amount',
        'payment_method',
        'collection_mode',
        'settlement_status',
        'settled_at',
        'commission_invoice_id',
    ];

    protected $casts = [
        'order_total' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'settled_at' => 'datetime',
    ];

    /**
     * Public API alias for collection_mode (tests and admin JSON).
     */
    public function getDeductionMethodAttribute(): ?string
    {
        $mode = $this->attributes['collection_mode'] ?? null;

        return match ($mode) {
            'automatic_split', 'gateway_split' => 'gateway_split',
            'manual_direct', 'accrual' => 'accrual',
            default => $mode,
        };
    }

    /**
     * Public API alias for settlement_status.
     */
    public function getStatusAttribute(): ?string
    {
        $status = $this->attributes['settlement_status'] ?? null;

        return match ($status) {
            'accrued' => 'pending',
            default => $status,
        };
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CommissionInvoice::class, 'commission_invoice_id');
    }
}
