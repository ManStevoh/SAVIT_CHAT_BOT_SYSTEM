<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['message' => 'No company.'], 403);
        }
        $settings = $company->settings()->first();
        return response()->json([
            'companyName' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address ?? '',
            'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
            'whatsappNumber' => $settings?->whatsapp_number,
            'aiGreeting' => $settings?->ai_greeting,
            'aiTone' => $settings?->ai_tone,
            'fallbackMessage' => $settings?->fallback_message,
            'awayMessage' => $settings?->away_message,
            'timezone' => $settings?->timezone ?? 'UTC',
            'workingHours' => $settings?->working_hours,
            'learnFromConversations' => ($settings?->learn_from_conversations ?? true) !== false,
            'autoReplyEnabled' => (bool) ($settings?->auto_reply_enabled ?? false),
            'notificationsEnabled' => (bool) ($settings?->notifications_enabled ?? false),
            'ordersAcceptMpesa' => (bool) ($settings?->orders_accept_mpesa ?? false),
            'ordersAcceptStripe' => (bool) ($settings?->orders_accept_stripe ?? false),
            'ordersAcceptPaystack' => (bool) ($settings?->orders_accept_paystack ?? false),
            'ordersCollectPaymentEnabled' => ($settings?->orders_collect_payment_enabled ?? true) !== false,
            'orderPaymentManualInstructions' => $settings?->order_payment_manual_instructions ?? '',
            'orderPaymentMpesaConfigured' => $settings?->hasOrderPaymentMpesaConfig() ?? false,
            'orderPaymentStripeConfigured' => $settings?->hasOrderPaymentStripeConfig() ?? false,
            'orderPaymentMpesaConfig' => $settings ? $this->maskOrderPaymentMpesaConfig($settings->order_payment_mpesa_config) : null,
            'orderPaymentStripeConfig' => $settings ? $this->maskOrderPaymentStripeConfig($settings->order_payment_stripe_config) : null,
            'displayCurrency' => $settings?->displayCurrencyCode() ?? 'USD',
            'industry' => $company->industry ?? 'other',
            'attributionRetentionDays' => $company->attribution_retention_days,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'No company.'], 403);
        }

        if ($request->hasFile('logo')) {
            $request->validate(['logo' => 'image|max:2048']);
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $company->logo = $request->file('logo')->store('logos/' . $company->id, 'public');
            $company->save();
        }

        $companyValidated = $request->validate([
            'companyName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'whatsappNumber' => 'nullable|string|max:50',
            'aiGreeting' => 'nullable|string',
            'aiTone' => 'nullable|string|max:255',
            'fallbackMessage' => 'nullable|string',
            'awayMessage' => 'nullable|string',
            'timezone' => 'nullable|string|max:50',
            'workingHours' => 'nullable|array',
            'workingHours.*' => 'nullable|string|max:50',
            'learnFromConversations' => 'sometimes|boolean',
            'autoReplyEnabled' => 'sometimes|boolean',
            'notificationsEnabled' => 'sometimes|boolean',
            'ordersAcceptMpesa' => 'sometimes|boolean',
            'ordersAcceptStripe' => 'sometimes|boolean',
            'ordersAcceptPaystack' => 'sometimes|boolean',
            'attributionRetentionDays' => 'sometimes|nullable|integer|min:30|max:730',
            'ordersCollectPaymentEnabled' => 'sometimes|boolean',
            'orderPaymentManualInstructions' => 'sometimes|nullable|string|max:2000',
            'orderPaymentMpesaConfig' => 'sometimes|nullable|array',
            'orderPaymentMpesaConfig.type' => 'nullable|string|in:paybill,till',
            'orderPaymentMpesaConfig.shortcode' => 'nullable|string|max:20',
            'orderPaymentMpesaConfig.passkey' => 'nullable|string|max:255',
            'orderPaymentMpesaConfig.consumer_key' => 'nullable|string|max:255',
            'orderPaymentMpesaConfig.consumer_secret' => 'nullable|string|max:255',
            'orderPaymentMpesaConfig.env' => 'nullable|string|in:sandbox,production',
            'orderPaymentStripeConfig' => 'sometimes|nullable|array',
            'orderPaymentStripeConfig.secret' => 'nullable|string|max:255',
            'orderPaymentStripeConfig.currency' => 'nullable|string|max:10',
            'displayCurrency' => 'sometimes|nullable|string|size:3',
            'industry' => 'sometimes|nullable|string|in:retail,restaurant,services,other',
        ]);

        if (isset($companyValidated['companyName'])) {
            $company->update(['name' => $companyValidated['companyName']]);
        }
        if (isset($companyValidated['email'])) {
            $company->update(['email' => $companyValidated['email']]);
        }
        if (array_key_exists('phone', $companyValidated)) {
            $company->update(['phone' => $companyValidated['phone']]);
        }
        if (array_key_exists('address', $companyValidated)) {
            $company->update(['address' => $companyValidated['address']]);
        }
        if (array_key_exists('industry', $companyValidated)) {
            $company->update(['industry' => $companyValidated['industry'] ?? 'other']);
        }
        if (array_key_exists('attributionRetentionDays', $companyValidated)) {
            $company->update(['attribution_retention_days' => $companyValidated['attributionRetentionDays']]);
        }

        $settings = $company->settings()->firstOrNew([]);
        $settings->company_id = $company->id;
        if (array_key_exists('whatsappNumber', $companyValidated)) {
            $settings->whatsapp_number = $companyValidated['whatsappNumber'];
        }
        if (array_key_exists('aiGreeting', $companyValidated)) {
            $settings->ai_greeting = $companyValidated['aiGreeting'];
        }
        if (array_key_exists('aiTone', $companyValidated)) {
            $settings->ai_tone = $companyValidated['aiTone'];
        }
        if (array_key_exists('fallbackMessage', $companyValidated)) {
            $settings->fallback_message = $companyValidated['fallbackMessage'];
        }
        if (array_key_exists('awayMessage', $companyValidated)) {
            $settings->away_message = $companyValidated['awayMessage'];
        }
        if (array_key_exists('timezone', $companyValidated)) {
            $settings->timezone = $companyValidated['timezone'] ?? 'UTC';
        }
        if (array_key_exists('workingHours', $companyValidated)) {
            $settings->working_hours = $companyValidated['workingHours'];
        }
        if (array_key_exists('learnFromConversations', $companyValidated)) {
            $settings->learn_from_conversations = $companyValidated['learnFromConversations'];
        }
        if (array_key_exists('autoReplyEnabled', $companyValidated)) {
            $settings->auto_reply_enabled = $companyValidated['autoReplyEnabled'];
        }
        if (array_key_exists('notificationsEnabled', $companyValidated)) {
            $settings->notifications_enabled = $companyValidated['notificationsEnabled'];
        }
        if (array_key_exists('ordersAcceptMpesa', $companyValidated)) {
            $settings->orders_accept_mpesa = $companyValidated['ordersAcceptMpesa'];
        }
        if (array_key_exists('ordersAcceptStripe', $companyValidated)) {
            $settings->orders_accept_stripe = $companyValidated['ordersAcceptStripe'];
        }
        if (array_key_exists('ordersAcceptPaystack', $companyValidated)) {
            $settings->orders_accept_paystack = $companyValidated['ordersAcceptPaystack'];
        }
        if (array_key_exists('ordersCollectPaymentEnabled', $companyValidated)) {
            $settings->orders_collect_payment_enabled = $companyValidated['ordersCollectPaymentEnabled'];
        }
        if (array_key_exists('orderPaymentManualInstructions', $companyValidated)) {
            $v = $companyValidated['orderPaymentManualInstructions'];
            $settings->order_payment_manual_instructions = (is_string($v) && trim($v) !== '') ? trim($v) : null;
        }
        if (array_key_exists('orderPaymentMpesaConfig', $companyValidated)) {
            $v = $companyValidated['orderPaymentMpesaConfig'];
            if ($v === null) {
                $settings->order_payment_mpesa_config = null;
            } elseif (is_array($v)) {
                $existing = $settings->order_payment_mpesa_config ?? [];
                if (array_key_exists('shortcode', $v)) {
                    $shortcode = trim((string) $v['shortcode']);
                    if ($shortcode === '') {
                        $shortcode = (string) ($existing['shortcode'] ?? '');
                    }
                } else {
                    $shortcode = (string) ($existing['shortcode'] ?? '');
                }
                if (array_key_exists('passkey', $v)) {
                    $passkey = trim((string) $v['passkey']);
                    if ($passkey === '' || $this->isMaskedSecretInput($passkey)) {
                        $passkey = (string) ($existing['passkey'] ?? '');
                    }
                } else {
                    $passkey = (string) ($existing['passkey'] ?? '');
                }
                if (array_key_exists('consumer_secret', $v)) {
                    $consumerSecret = trim((string) $v['consumer_secret']);
                    if ($consumerSecret === '' || $this->isMaskedSecretInput($consumerSecret)) {
                        $consumerSecret = isset($existing['consumer_secret']) ? (string) $existing['consumer_secret'] : '';
                    }
                } else {
                    $consumerSecret = isset($existing['consumer_secret']) ? (string) $existing['consumer_secret'] : '';
                }
                if (array_key_exists('consumer_key', $v)) {
                    $consumerKey = trim((string) $v['consumer_key']);
                    if ($this->isMaskedSecretInput($consumerKey)) {
                        $consumerKey = isset($existing['consumer_key']) ? (string) $existing['consumer_key'] : '';
                    }
                } else {
                    $consumerKey = isset($existing['consumer_key']) ? (string) $existing['consumer_key'] : '';
                }
                $type = in_array($v['type'] ?? null, ['paybill', 'till'], true)
                    ? $v['type']
                    : ($existing['type'] ?? 'paybill');
                $env = in_array($v['env'] ?? null, ['sandbox', 'production'], true)
                    ? $v['env']
                    : ($existing['env'] ?? 'sandbox');

                if ($shortcode !== '' && $passkey !== '') {
                    $settings->order_payment_mpesa_config = [
                        'type' => $type,
                        'shortcode' => $shortcode,
                        'passkey' => $passkey,
                        'consumer_key' => $consumerKey !== '' ? $consumerKey : null,
                        'consumer_secret' => $consumerSecret !== '' ? $consumerSecret : null,
                        'env' => $env,
                    ];
                } else {
                    $settings->order_payment_mpesa_config = null;
                }
            }
        }
        if (array_key_exists('displayCurrency', $companyValidated)) {
            $raw = $companyValidated['displayCurrency'] ?? null;
            $code = is_string($raw) ? strtoupper(preg_replace('/[^A-Za-z]/', '', $raw) ?? '') : '';
            $settings->display_currency = strlen($code) === 3 ? $code : 'USD';
        }
        if (array_key_exists('orderPaymentStripeConfig', $companyValidated)) {
            $v = $companyValidated['orderPaymentStripeConfig'];
            if ($v === null) {
                $settings->order_payment_stripe_config = null;
            } elseif (is_array($v)) {
                $existing = $settings->order_payment_stripe_config ?? [];
                if (array_key_exists('secret', $v)) {
                    $secret = trim((string) $v['secret']);
                    if ($secret === '' || $this->isMaskedSecretInput($secret)) {
                        $secret = (string) ($existing['secret'] ?? '');
                    }
                } else {
                    $secret = (string) ($existing['secret'] ?? '');
                }
                if ($secret !== '') {
                    $currency = isset($v['currency']) ? trim((string) $v['currency']) : '';
                    if ($currency === '') {
                        $currency = (string) ($existing['currency'] ?? 'usd');
                    }
                    $settings->order_payment_stripe_config = [
                        'secret' => $secret,
                        'currency' => $currency !== '' ? $currency : 'usd',
                    ];
                } else {
                    $settings->order_payment_stripe_config = null;
                }
            }
        }
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    protected function maskOrderPaymentMpesaConfig(?array $config): ?array
    {
        if ($config === null || $config === []) {
            return null;
        }
        $out = $config;
        foreach (['passkey', 'consumer_secret'] as $key) {
            if (! empty($out[$key]) && is_string($out[$key])) {
                $out[$key] = $this->maskSecretString($out[$key]);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    protected function maskOrderPaymentStripeConfig(?array $config): ?array
    {
        if ($config === null || $config === []) {
            return null;
        }
        $out = $config;
        if (! empty($out['secret']) && is_string($out['secret'])) {
            $out['secret'] = $this->maskSecretString($out['secret']);
        }

        return $out;
    }

    protected function maskSecretString(string $value): string
    {
        if (strlen($value) > 4) {
            return '••••••••'.substr($value, -4);
        }

        return '••••••••'.$value;
    }

    protected function isMaskedSecretInput(string $value): bool
    {
        return str_starts_with($value, '••••') || $value === '';
    }
}
