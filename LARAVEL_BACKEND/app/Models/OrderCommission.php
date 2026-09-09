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
