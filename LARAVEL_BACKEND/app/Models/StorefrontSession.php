<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StorefrontSession extends Model
{
    protected $fillable = [
        'company_id',
        'session_token',
        'customer_name',
        'customer_phone',
        'cart',
        'fulfillment_type',
        'dine_in_table_id',
    ];

    protected $casts = [
        'cart' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (StorefrontSession $session): void {
            if (empty($session->session_token)) {
                $session->session_token = Str::random(48);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function dineInTable(): BelongsTo
    {
        return $this->belongsTo(DineInTable::class, 'dine_in_table_id');
    }
}
