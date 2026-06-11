<?php

namespace App\Models;

use App\Support\MoneyFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySetting extends Model
{
    protected $fillable = [
        'company_id',
        'display_currency',
        'whatsapp_number',
        'ai_greeting',
        'ai_tone',
        'fallback_message',
        'away_message',
        'timezone',
        'working_hours',
        'learn_from_conversations',
        'auto_reply_enabled',
        'notifications_enabled',
        'orders_accept_mpesa',
        'orders_accept_stripe',
        'orders_accept_paystack',
        'orders_collect_payment_enabled',
        'order_payment_mpesa_config',
        'order_payment_stripe_config',
        'order_payment_manual_instructions',
    ];

    protected $casts = [
        'auto_reply_enabled' => 'boolean',
        'notifications_enabled' => 'boolean',
        'orders_accept_mpesa' => 'boolean',
        'orders_accept_stripe' => 'boolean',
        'orders_accept_paystack' => 'boolean',
        'orders_collect_payment_enabled' => 'boolean',
        'order_payment_mpesa_config' => 'array',
        'order_payment_stripe_config' => 'array',
        'working_hours' => 'array',
        'learn_from_conversations' => 'boolean',
    ];

    /** Whether company has its own M-Pesa config for order payments (shortcode + passkey). */
    public function hasOrderPaymentMpesaConfig(): bool
    {
        $c = $this->order_payment_mpesa_config;
        return is_array($c) && ! empty($c['shortcode']) && ! empty($c['passkey']);
    }

    /** Whether company has its own Stripe config for order payments (secret). */
    public function hasOrderPaymentStripeConfig(): bool
    {
        $c = $this->order_payment_stripe_config;
        return is_array($c) && ! empty($c['secret']);
    }

    /** Whether company has manual payment instructions (e.g. bank details) for orders. */
    public function hasOrderPaymentManualInstructions(): bool
    {
        $t = $this->order_payment_manual_instructions;
        return is_string($t) && trim($t) !== '';
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** ISO 4217 code for catalog and chat price display (e.g. USD, KES, EGP). */
    public function displayCurrencyCode(): string
    {
        return MoneyFormatter::normalizeCurrencyCode($this->display_currency);
    }
}
