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
            'ordersCollectPaymentEnabled' => ($settings?->orders_collect_payment_enabled ?? true) !== false,
            'orderPaymentManualInstructions' => $settings?->order_payment_manual_instructions ?? '',
            'orderPaymentMpesaConfigured' => $settings?->hasOrderPaymentMpesaConfig() ?? false,
            'orderPaymentStripeConfigured' => $settings?->hasOrderPaymentStripeConfig() ?? false,
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
            'ordersCollectPaymentEnabled' => 'sometimes|boolean',
            'orderPaymentManualInstructions' => 'sometimes|nullable|string|max:2000',
            'orderPaymentMpesaConfig' => 'sometimes|nullable|array',
            'orderPaymentMpesaConfig.type' => 'nullable|string|in:paybill,till',
            'orderPaymentMpesaConfig.shortcode' => 'required_with:orderPaymentMpesaConfig|nullable|string|max:20',
            'orderPaymentMpesaConfig.passkey' => 'required_with:orderPaymentMpesaConfig|nullable|string|max:255',
            'orderPaymentMpesaConfig.consumer_key' => 'nullable|string|max:255',
            'orderPaymentMpesaConfig.consumer_secret' => 'nullable|string|max:255',
            'orderPaymentMpesaConfig.env' => 'nullable|string|in:sandbox,production',
            'orderPaymentStripeConfig' => 'sometimes|nullable|array',
            'orderPaymentStripeConfig.secret' => 'required_with:orderPaymentStripeConfig|nullable|string|max:255',
            'orderPaymentStripeConfig.currency' => 'nullable|string|max:10',
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
        if (array_key_exists('ordersCollectPaymentEnabled', $companyValidated)) {
            $settings->orders_collect_payment_enabled = $companyValidated['ordersCollectPaymentEnabled'];
        }
        if (array_key_exists('orderPaymentManualInstructions', $companyValidated)) {
            $v = $companyValidated['orderPaymentManualInstructions'];
            $settings->order_payment_manual_instructions = (is_string($v) && trim($v) !== '') ? trim($v) : null;
        }
        if (array_key_exists('orderPaymentMpesaConfig', $companyValidated)) {
            $v = $companyValidated['orderPaymentMpesaConfig'];
            if (is_array($v) && ! empty(trim((string) ($v['shortcode'] ?? ''))) && ! empty(trim((string) ($v['passkey'] ?? ''))) {
                $settings->order_payment_mpesa_config = [
                    'type' => in_array($v['type'] ?? null, ['paybill', 'till'], true) ? $v['type'] : 'paybill',
                    'shortcode' => trim((string) $v['shortcode']),
                    'passkey' => trim((string) $v['passkey']),
                    'consumer_key' => isset($v['consumer_key']) ? trim((string) $v['consumer_key']) : null,
                    'consumer_secret' => isset($v['consumer_secret']) ? trim((string) $v['consumer_secret']) : null,
                    'env' => in_array($v['env'] ?? null, ['sandbox', 'production'], true) ? $v['env'] : 'sandbox',
                ];
            } else {
                $settings->order_payment_mpesa_config = null;
            }
        }
        if (array_key_exists('orderPaymentStripeConfig', $companyValidated)) {
            $v = $companyValidated['orderPaymentStripeConfig'];
            if (is_array($v) && ! empty(trim((string) ($v['secret'] ?? ''))) {
                $settings->order_payment_stripe_config = [
                    'secret' => trim((string) $v['secret']),
                    'currency' => isset($v['currency']) ? trim((string) $v['currency']) : 'usd',
                ];
            } else {
                $settings->order_payment_stripe_config = null;
            }
        }
        $settings->save();

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
        ]);
    }
}
