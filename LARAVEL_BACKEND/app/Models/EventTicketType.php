<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTicketType extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'company_id',
        'event_id',
        'event_session_id',
        'product_id',
        'name',
        'description',
        'price',
        'currency',
        'quantity_total',
        'sales_start_at',
        'sales_end_at',
        'min_per_order',
        'max_per_order',
        'is_free',
        'status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity_total' => 'int',
        'sales_start_at' => 'datetime',
        'sales_end_at' => 'datetime',
        'min_per_order' => 'int',
        'max_per_order' => 'int',
        'is_free' => 'bool',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(EventSession::class, 'event_session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    public function isOnSale(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        $now = now();
        if ($this->sales_start_at && $now->lt($this->sales_start_at)) {
            return false;
        }
        if ($this->sales_end_at && $now->gt($this->sales_end_at)) {
            return false;
        }

        return true;
    }
}
