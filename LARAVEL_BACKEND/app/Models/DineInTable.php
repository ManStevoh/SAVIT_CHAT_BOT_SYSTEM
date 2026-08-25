<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DineInTable extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'qr_token',
        'seats',
        'is_active',
    ];

    protected $casts = [
        'seats' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (DineInTable $table): void {
            if (empty($table->qr_token)) {
                $table->qr_token = Str::random(40);
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'dine_in_table_id');
    }

    public function publicOrderUrl(): string
    {
        $slug = $this->company?->store_slug;
        if (! $slug) {
            return url('/t/'.$this->qr_token);
        }

        return url('/s/'.$slug.'/table/'.$this->qr_token);
    }

    public function whatsappOrderUrl(): ?string
    {
        $phone = $this->company?->whatsapp_number ?? $this->company?->settings?->whatsapp_number;
        if (! $phone) {
            return null;
        }

        $cleanPhone = preg_replace('/[^\d]/', '', (string) $phone);
        if ($cleanPhone === '') {
            return null;
        }

        $tableName = $this->name ?: ('Table '.$this->code);
        $message = "Hi! I am at {$tableName} (Ref: T{$this->code}). I would like to view the menu and place an order.";

        return 'https://wa.me/'.$cleanPhone.'?text='.rawurlencode($message);
    }

    public function targetQrUrl(): string
    {
        $target = $this->company?->settings?->dine_in_qr_target ?? 'web_menu';
        if ($target === 'whatsapp_chat') {
            $wa = $this->whatsappOrderUrl();
            if ($wa) {
                return $wa;
            }
        }

        return $this->publicOrderUrl();
    }
}

