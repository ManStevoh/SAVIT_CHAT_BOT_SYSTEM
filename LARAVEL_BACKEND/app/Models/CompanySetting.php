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
        'currency_symbol',
        'thousands_separator',
        'decimal_separator',
        'tax_enabled',
        'whatsapp_number',
        'ai_greeting',
        'ai_tone',
        'ai_model_mode',
        'ai_model_id',
        'ai_reply_mode',
        'ai_credential_mode',
        'default_reply_language',
        'reply_in_customer_language',
        'fallback_message',
        'away_message',
        'timezone',
        'working_hours',
        'learn_from_conversations',
        'dev_mode_enabled',
        'agent_commerce_enabled',
        'agent_business_goals',
        'agent_proactive_enabled',
        'web_widget_token',
        'channel_ingest_secret',
        'agent_voice_reply_enabled',
        'agent_voice_reply_mode',
        'agent_voice_id',
        'agent_morning_brief_whatsapp_enabled',
        'owner_whatsapp_phone',
        'consciousness_last_sensed_at',
        'digital_twin',
        'agent_council_enabled',
        'business_dna',
        'auto_reply_enabled',
        'notifications_enabled',
        'orders_accept_mpesa',
        'orders_accept_stripe',
        'orders_accept_paystack',
        'orders_accept_pesapal',
        'orders_accept_flutterwave',
        'orders_accept_cod',
        'orders_collect_payment_enabled',
        'order_payment_mpesa_config',
        'order_payment_stripe_config',
        'order_payment_paystack_config',
        'order_payment_pesapal_config',
        'order_payment_flutterwave_config',
        'order_payment_manual_instructions',
        'delivery_fees_enabled',
        'default_delivery_fee',
        'free_delivery_above',
        'payment_recovery_enabled',
        'payment_recovery_hours',
        'birthday_automation_enabled',
        'birthday_coupon_percent',
        'birthday_message_template',
        'winback_automation_enabled',
        'winback_days_inactive',
        'spam_order_protection_enabled',
        'spam_max_orders_per_hour',
        'spam_max_orders_per_day',
        'dine_in_enabled',
        'abandoned_cart_recovery_enabled',
        'storefront_whatsapp_order_notify',
        'abandoned_cart_template_name',
        'storefront_alt_currencies',
        'storefront_default_locale',
    ];

    protected $casts = [
        'tax_enabled' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'notifications_enabled' => 'boolean',
        'orders_accept_mpesa' => 'boolean',
        'orders_accept_stripe' => 'boolean',
        'orders_accept_paystack' => 'boolean',
        'orders_accept_pesapal' => 'boolean',
        'orders_accept_flutterwave' => 'boolean',
        'orders_accept_cod' => 'boolean',
        'orders_collect_payment_enabled' => 'boolean',
        'order_payment_mpesa_config' => 'array',
        'order_payment_stripe_config' => 'array',
        'order_payment_paystack_config' => 'array',
        'order_payment_pesapal_config' => 'array',
        'order_payment_flutterwave_config' => 'array',
        'delivery_fees_enabled' => 'boolean',
        'default_delivery_fee' => 'decimal:2',
        'free_delivery_above' => 'decimal:2',
        'payment_recovery_enabled' => 'boolean',
        'payment_recovery_hours' => 'array',
        'birthday_automation_enabled' => 'boolean',
        'birthday_coupon_percent' => 'integer',
        'winback_automation_enabled' => 'boolean',
        'winback_days_inactive' => 'integer',
        'spam_order_protection_enabled' => 'boolean',
        'spam_max_orders_per_hour' => 'integer',
        'spam_max_orders_per_day' => 'integer',
        'dine_in_enabled' => 'boolean',
        'working_hours' => 'array',
        'learn_from_conversations' => 'boolean',
        'dev_mode_enabled' => 'boolean',
        'agent_commerce_enabled' => 'boolean',
        'agent_business_goals' => 'array',
        'agent_proactive_enabled' => 'boolean',
        'agent_voice_reply_enabled' => 'boolean',
        'agent_morning_brief_whatsapp_enabled' => 'boolean',
        'consciousness_last_sensed_at' => 'datetime',
        'digital_twin' => 'array',
        'agent_council_enabled' => 'boolean',
        'business_dna' => 'array',
        'reply_in_customer_language' => 'boolean',
        'abandoned_cart_recovery_enabled' => 'boolean',
        'storefront_whatsapp_order_notify' => 'boolean',
        'storefront_alt_currencies' => 'array',
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

    /** Whether company has its own Paystack config for order payments (secret_key or public_key). */
    public function hasOrderPaymentPaystackConfig(): bool
    {
        $c = $this->order_payment_paystack_config;
        return is_array($c) && (! empty($c['secret_key']) || ! empty($c['public_key']));
    }

    /** Whether company has its own Pesapal config for order payments (consumer_key and consumer_secret). */
    public function hasOrderPaymentPesapalConfig(): bool
    {
        $c = $this->order_payment_pesapal_config;
        return is_array($c) && ! empty($c['consumer_key']) && ! empty($c['consumer_secret']);
    }

    protected static function booted(): void
    {
        static::saved(function (CompanySetting $setting) {
            \Illuminate\Support\Facades\Cache::forget('company_settings_'.$setting->company_id);
        });
    }

    /** Whether company has manual payment instructions (e.g. bank details) for orders. */
    public function hasOrderPaymentManualInstructions(): bool
    {
        $t = $this->order_payment_manual_instructions;
        return is_string($t) && trim($t) !== '';
    }

    /** @return list<int> */
    public function paymentRecoveryHourOffsets(): array
    {
        $hours = $this->payment_recovery_hours;
        if (! is_array($hours) || $hours === []) {
            return [1, 24, 72];
        }

        return array_values(array_unique(array_map('intval', $hours)));
    }

    /**
     * @return list<array{code: string, rate: float}>
     */
    public function altCurrencyOptions(): array
    {
        $raw = $this->storefront_alt_currencies;
        if (! is_array($raw)) {
            return [];
        }

        return collect($raw)
            ->filter(fn ($row) => is_array($row) && ! empty($row['code']) && isset($row['rate']))
            ->map(fn ($row) => [
                'code' => MoneyFormatter::normalizeCurrencyCode((string) $row['code']),
                'rate' => (float) $row['rate'],
            ])
            ->values()
            ->all();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }

    public function hasOrderPaymentFlutterwaveConfig(): bool
    {
        $c = $this->order_payment_flutterwave_config;

        return is_array($c) && ! empty($c['secret_key']);
    }

    /** ISO 4217 code for catalog and chat price display (e.g. USD, KES, EGP). */
    public function displayCurrencyCode(): string
    {
        return MoneyFormatter::normalizeCurrencyCode($this->display_currency);
    }

    /**
     * @return array{symbol: ?string, thousands: string, decimal: string}
     */
    public function moneyDisplayOptions(): array
    {
        return MoneyFormatter::displayOptionsFromSettings($this);
    }
}
