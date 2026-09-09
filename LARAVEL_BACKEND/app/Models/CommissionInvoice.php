<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'invoice_number',
        'period_start',
        'period_end',
        'orders_count',
        'gross_sales',
        'amount_due',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        'payment_method',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'orders_count' => 'integer',
        'gross_sales' => 'decimal:2',
        'amount_due' => 'decimal:2',
        'due_date' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(OrderCommission::class, 'commission_invoice_id');
    }
}
