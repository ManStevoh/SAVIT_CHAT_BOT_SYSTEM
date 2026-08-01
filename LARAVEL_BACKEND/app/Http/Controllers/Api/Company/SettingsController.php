<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Services\Agent\BusinessGoalService;
use App\Services\Agent\Cognitive\BusinessDnaService;
use App\Services\Agent\CommerceAgentReplyService;
use App\Services\Agent\Company\CompanyDigitalTwinService;
use App\Services\AI\AiLearningConfig;
use App\Services\PlanLimitService;
use App\Support\MoneyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        $learningConfig = app(AiLearningConfig::class);
        $plan = PlanLimitService::getCurrentPlanSlug($company);
        $dnaService = app(BusinessDnaService::class);
        $twinService = app(CompanyDigitalTwinService::class);
        $storedDna = $settings?->business_dna;
        $storedTwin = $settings?->digital_twin;

        return response()->json([
            'companyId' => $company->id,
            'companyName' => $company->name,
            'email' => $company->email,
            'phone' => $company->phone,
            'address' => $company->address ?? '',
            'logo' => $company->logo ? asset('storage/' . $company->logo) : null,
            'whatsappNumber' => $settings?->whatsapp_number,
            'aiGreeting' => $settings?->ai_greeting,
            'aiTone' => $settings?->ai_tone,
            'aiModelMode' => $settings?->ai_model_mode ?? 'auto',
            'effectiveAiModelMode' => PlanLimitService::effectiveAiModelMode($company),
            'aiModelId' => $settings?->ai_model_id ? (string) $settings->ai_model_id : null,
            'aiReplyMode' => $settings?->ai_reply_mode ?? config('conversation.default_reply_mode', 'ai_first'),
            'aiCredentialMode' => $settings?->ai_credential_mode ?? 'platform',
            'effectiveAiCredentialMode' => PlanLimitService::effectiveCredentialMode($company),
            'aiPlanCapabilities' => PlanLimitService::aiPlanCapabilities($plan),
            'defaultReplyLanguage' => $settings?->default_reply_language,
            'replyInCustomerLanguage' => ($settings?->reply_in_customer_language ?? true) !== false,
            'fallbackMessage' => $settings?->fallback_message,
            'awayMessage' => $settings?->away_message,
            'timezone' => $settings?->timezone ?? 'UTC',
            'workingHours' => $settings?->working_hours,
            'learnFromConversations' => $learningConfig->companyLearnFromChatsEnabled($company),
            'devModeEnabled' => (bool) ($settings?->dev_mode_enabled ?? false),
            'learnFromConversationsEditable' => $learningConfig->isLearningEnabled()
                && (bool) ($learningConfig->all()['allowCompanyOverride'] ?? true),
            'aiLearningEnabled' => $learningConfig->isLearningEnabled(),
            'autoReplyEnabled' => ($settings?->auto_reply_enabled ?? true) !== false,
            'agentCommerceEnabled' => (bool) ($settings?->agent_commerce_enabled ?? config('agent.default_agent_commerce_enabled', false)),
            'agentCommerceEntitled' => \App\Services\Agent\CommerceAgentReplyService::isEntitledForCompany($company),
            'agentProactiveEnabled' => (bool) ($settings?->agent_proactive_enabled ?? false),
            'agentBusinessGoals' => $settings?->agent_business_goals ?? app(BusinessGoalService::class)->enabledKeys($company),
            'agentBusinessGoalCatalog' => app(BusinessGoalService::class)->catalog(),
            'businessDna' => $dnaService->resolve($company),
            'businessDnaCustom' => is_array($storedDna) && $storedDna !== [],
            'businessDnaPresets' => config('agent.cognitive.business_dna_presets', []),
            'digitalTwin' => $twinService->getTwin($company),
            'digitalTwinCustom' => is_array($storedTwin) && $storedTwin !== [],
            'digitalTwinFields' => config('agent.company.digital_twin_fields', []),
            'agentCouncilEnabled' => (bool) ($settings?->agent_council_enabled ?? false),
            'agentVoiceReplyEnabled' => (bool) ($settings?->agent_voice_reply_enabled ?? false),
            'agentMorningBriefWhatsappEnabled' => (bool) ($settings?->agent_morning_brief_whatsapp_enabled ?? false),
            'ownerWhatsappPhone' => $settings?->owner_whatsapp_phone,
            'consciousnessLastSensedAt' => $settings?->consciousness_last_sensed_at?->toIso8601String(),
            'webWidgetToken' => $settings?->web_widget_token,
            'channelIngestSecret' => $settings?->channel_ingest_secret,
            'channelWebhookUrls' => [
                'email' => rtrim(config('app.url'), '/').'/api/webhooks/channels/'.$company->id.'/email',
                'instagramDm' => rtrim(config('app.url'), '/').'/api/webhooks/channels/'.$company->id.'/instagram-dm',
            ],
            'widgetScriptUrl' => rtrim(config('app.url'), '/').'/widget/savit-chat.js',
            'notificationsEnabled' => (bool) ($settings?->notifications_enabled ?? false),
            'ordersAcceptMpesa' => (bool) ($settings?->orders_accept_mpesa ?? false),
            'ordersAcceptStripe' => (bool) ($settings?->orders_accept_stripe ?? false),
            'ordersAcceptPaystack' => (bool) ($settings?->orders_accept_paystack ?? false),
            'ordersAcceptPesapal' => (bool) ($settings?->orders_accept_pesapal ?? false),
            'ordersCollectPaymentEnabled' => ($settings?->orders_collect_payment_enabled ?? true) !== false,
            'orderPaymentManualInstructions' => $settings?->order_payment_manual_instructions ?? '',
            'orderPaymentMpesaConfigured' => $settings?->hasOrderPaymentMpesaConfig() ?? false,
            'orderPaymentStripeConfigured' => $settings?->hasOrderPaymentStripeConfig() ?? false,
            'orderPaymentPaystackConfigured' => $settings?->hasOrderPaymentPaystackConfig() ?? false,
            'orderPaymentPesapalConfigured' => $settings?->hasOrderPaymentPesapalConfig() ?? false,
            'orderPaymentMpesaConfig' => $settings ? $this->maskOrderPaymentMpesaConfig($settings->order_payment_mpesa_config) : null,
            'orderPaymentStripeConfig' => $settings ? $this->maskOrderPaymentStripeConfig($settings->order_payment_stripe_config) : null,
            'orderPaymentPaystackConfig' => $settings ? $this->maskOrderPaymentPaystackConfig($settings->order_payment_paystack_config) : null,
            'orderPaymentPesapalConfig' => $settings ? $this->maskOrderPaymentPesapalConfig($settings->order_payment_pesapal_config) : null,
            'displayCurrency' => $settings?->displayCurrencyCode() ?? 'USD',
            'currencySymbol' => $settings?->currency_symbol,
            'thousandsSeparator' => MoneyFormatter::normalizeThousands($settings?->thousands_separator),
            'decimalSeparator' => MoneyFormatter::normalizeDecimal(
                $settings?->decimal_separator,
                MoneyFormatter::normalizeThousands($settings?->thousands_separator)
            ),
            'taxEnabled' => (bool) ($settings?->tax_enabled ?? false),
            'industry' => $company->industry ?? 'other',
            'attributionRetentionDays' => $company->attribution_retention_days,
            'storeSlug' => $company->store_slug,
            'storefrontEnabled' => (bool) $company->storefront_enabled,
            'storefrontUrl' => $company->store_slug
                ? rtrim(config('app.url'), '/') . '/s/' . $company->store_slug
                : null,
            'linkInBioEnabled' => (bool) $company->link_in_bio_enabled,
            'linkInBioHeadline' => $company->link_in_bio_headline,
            'linkInBioBio' => $company->link_in_bio_bio,
            'linkInBioLinks' => is_array($company->link_in_bio_links) ? $company->link_in_bio_links : [],
            'linkInBioUrl' => $company->store_slug ? rtrim(config('app.url'), '/').'/b/'.$company->store_slug : null,
            'ordersAcceptCod' => (bool) ($settings?->orders_accept_cod ?? false),
            'deliveryFeesEnabled' => (bool) ($settings?->delivery_fees_enabled ?? false),
            'defaultDeliveryFee' => $settings?->default_delivery_fee !== null
                ? (float) $settings->default_delivery_fee
                : 0,
            'freeDeliveryAbove' => $settings?->free_delivery_above !== null
                ? (float) $settings->free_delivery_above
                : null,
            'dineInEnabled' => (bool) ($settings?->dine_in_enabled ?? false),
            'paymentRecoveryEnabled' => ($settings?->payment_recovery_enabled ?? true) !== false,
            'paymentRecoveryHours' => $settings?->paymentRecoveryHourOffsets() ?? [1, 24, 72],
            'abandonedCartRecoveryEnabled' => (bool) ($settings?->abandoned_cart_recovery_enabled ?? false),
            'storefrontWhatsappOrderNotify' => ($settings?->storefront_whatsapp_order_notify ?? true) !== false,
            'abandonedCartTemplateName' => $settings?->abandoned_cart_template_name ?? '',
            'birthdayAutomationEnabled' => (bool) ($settings?->birthday_automation_enabled ?? false),
            'birthdayCouponPercent' => $settings?->birthday_coupon_percent !== null ? (int) $settings->birthday_coupon_percent : 10,
            'birthdayMessageTemplate' => $settings?->birthday_message_template ?? '',
            'winbackAutomationEnabled' => (bool) ($settings?->winback_automation_enabled ?? false),
            'winbackDaysInactive' => $settings?->winback_days_inactive !== null ? (int) $settings->winback_days_inactive : 30,
            'spamOrderProtectionEnabled' => (bool) ($settings?->spam_order_protection_enabled ?? false),
            'spamMaxOrdersPerHour' => $settings?->spam_max_orders_per_hour !== null ? (int) $settings->spam_max_orders_per_hour : 5,
            'spamMaxOrdersPerDay' => $settings?->spam_max_orders_per_day !== null ? (int) $settings->spam_max_orders_per_day : 20,
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
            'aiModelMode' => 'sometimes|string|in:auto,platform_default,specific',
            'aiModelId' => 'nullable|required_if:aiModelMode,specific|integer|exists:ai_models,id',
            'aiReplyMode' => 'sometimes|string|in:ai_first,balanced',
            'aiCredentialMode' => 'sometimes|string|in:platform,company,company_preferred',
            'defaultReplyLanguage' => 'nullable|string|max:10',
            'replyInCustomerLanguage' => 'sometimes|boolean',
            'fallbackMessage' => 'nullable|string',
            'awayMessage' => 'nullable|string',
            'timezone' => 'nullable|string|max:50',
            'workingHours' => 'nullable|array',
            'workingHours.*' => 'nullable|string|max:50',
            'learnFromConversations' => 'sometimes|boolean',
            'agentCommerceEnabled' => 'sometimes|boolean',
            'agentProactiveEnabled' => 'sometimes|boolean',
            'agentVoiceReplyEnabled' => 'sometimes|boolean',
            'agentMorningBriefWhatsappEnabled' => 'sometimes|boolean',
            'ownerWhatsappPhone' => 'sometimes|nullable|string|max:32',
            'agentBusinessGoals' => 'sometimes|nullable|array',
            'agentBusinessGoals.*' => 'string|max:80',
            'businessDna' => 'sometimes|nullable|array',
            'businessDna.tone' => 'nullable|string|max:200',
            'businessDna.values' => 'nullable|array',
            'businessDna.values.*' => 'string|max:80',
            'businessDna.risk_tolerance' => 'nullable|string|in:low,medium,high',
            'businessDna.service_philosophy' => 'nullable|string|max:500',
            'businessDna.escalation_culture' => 'nullable|string|max:500',
            'businessDna.communication_style' => 'nullable|string|max:300',
            'digitalTwin' => 'sometimes|nullable|array',
            'digitalTwin.mission' => 'nullable|string|max:500',
            'digitalTwin.brand_voice' => 'nullable|string|max:300',
            'digitalTwin.sales_strategy' => 'nullable|string|max:500',
            'digitalTwin.pricing_rules' => 'nullable|string|max:500',
            'digitalTwin.competitors' => 'nullable|string|max:500',
            'digitalTwin.target_customers' => 'nullable|string|max:500',
            'agentCouncilEnabled' => 'sometimes|boolean',
            'devModeEnabled' => 'sometimes|boolean',
            'autoReplyEnabled' => 'sometimes|boolean',
            'notificationsEnabled' => 'sometimes|boolean',
            'ordersAcceptMpesa' => 'sometimes|boolean',
            'ordersAcceptStripe' => 'sometimes|boolean',
            'ordersAcceptPaystack' => 'sometimes|boolean',
            'ordersAcceptPesapal' => 'sometimes|boolean',
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
            'orderPaymentStripeConfig.env' => 'nullable|string|in:sandbox,production',
            'orderPaymentPaystackConfig' => 'sometimes|nullable|array',
            'orderPaymentPaystackConfig.secret_key' => 'nullable|string|max:255',
            'orderPaymentPaystackConfig.public_key' => 'nullable|string|max:255',
            'orderPaymentPaystackConfig.currency' => 'nullable|string|max:10',
            'orderPaymentPaystackConfig.env' => 'nullable|string|in:sandbox,production',
            'orderPaymentPesapalConfig' => 'sometimes|nullable|array',
            'orderPaymentPesapalConfig.consumer_key' => 'nullable|string|max:255',
            'orderPaymentPesapalConfig.consumer_secret' => 'nullable|string|max:255',
            'orderPaymentPesapalConfig.currency' => 'nullable|string|max:10',
            'orderPaymentPesapalConfig.env' => 'nullable|string|in:sandbox,production',
            'displayCurrency' => 'sometimes|nullable|string|size:3',
            'currencySymbol' => 'sometimes|nullable|string|max:16',
            'thousandsSeparator' => ['sometimes', 'nullable', 'string', Rule::in([',', '.', ' ', "'"])],
            'decimalSeparator' => ['sometimes', 'nullable', 'string', Rule::in([',', '.'])],
            'taxEnabled' => 'sometimes|boolean',
            'industry' => 'sometimes|nullable|string|in:retail,restaurant,services,other',
            'storeSlug' => [
                'sometimes',
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('companies', 'store_slug')->ignore($company->id),
            ],
            'storefrontEnabled' => 'sometimes|boolean',
            'linkInBioEnabled' => 'sometimes|boolean',
            'linkInBioHeadline' => 'sometimes|nullable|string|max:255',
            'linkInBioBio' => 'sometimes|nullable|string|max:2000',
            'linkInBioLinks' => 'sometimes|nullable|array|max:20',
            'linkInBioLinks.*.label' => 'required_with:linkInBioLinks|string|max:80',
            'linkInBioLinks.*.url' => 'required_with:linkInBioLinks|string|max:500',
            'ordersAcceptCod' => 'sometimes|boolean',
            'ordersAcceptBankTransfer' => 'sometimes|boolean',
            'bankTransferInstructions' => 'sometimes|nullable|string|max:2000',
            'deliveryFeesEnabled' => 'sometimes|boolean',
            'defaultDeliveryFee' => 'sometimes|nullable|numeric|min:0',
            'freeDeliveryAbove' => 'sometimes|nullable|numeric|min:0',
            'dineInEnabled' => 'sometimes|boolean',
            'paymentRecoveryEnabled' => 'sometimes|boolean',
            'paymentRecoveryHours' => 'sometimes|nullable|array',
            'paymentRecoveryHours.*' => 'integer|min:1|max:720',
            'abandonedCartRecoveryEnabled' => 'sometimes|boolean',
            'storefrontWhatsappOrderNotify' => 'sometimes|boolean',
            'abandonedCartTemplateName' => 'sometimes|nullable|string|max:128',
            'birthdayAutomationEnabled' => 'sometimes|boolean',
            'birthdayCouponPercent' => 'sometimes|nullable|integer|min:0|max:100',
            'birthdayMessageTemplate' => 'sometimes|nullable|string|max:1000',
            'winbackAutomationEnabled' => 'sometimes|boolean',
            'winbackDaysInactive' => 'sometimes|nullable|integer|min:7|max:365',
            'spamOrderProtectionEnabled' => 'sometimes|boolean',
            'spamMaxOrdersPerHour' => 'sometimes|nullable|integer|min:1|max:100',
            'spamMaxOrdersPerDay' => 'sometimes|nullable|integer|min:1|max:500',
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
        if (array_key_exists('storeSlug', $companyValidated)) {
            $slug = $companyValidated['storeSlug'];
            $company->store_slug = is_string($slug) && trim($slug) !== '' ? Str::slug(trim($slug)) : null;
        }
        if (array_key_exists('storefrontEnabled', $companyValidated)) {
            $company->storefront_enabled = $companyValidated['storefrontEnabled'];
        }
        if (array_key_exists('linkInBioEnabled', $companyValidated)) {
            $company->link_in_bio_enabled = $companyValidated['linkInBioEnabled'];
        }
        if (array_key_exists('linkInBioHeadline', $companyValidated)) {
            $headline = $companyValidated['linkInBioHeadline'];
            $company->link_in_bio_headline = is_string($headline) && trim($headline) !== '' ? trim($headline) : null;
        }
        if (array_key_exists('linkInBioBio', $companyValidated)) {
            $bio = $companyValidated['linkInBioBio'];
            $company->link_in_bio_bio = is_string($bio) && trim($bio) !== '' ? trim($bio) : null;
        }
        if (array_key_exists('linkInBioLinks', $companyValidated)) {
            $links = $companyValidated['linkInBioLinks'];
            $company->link_in_bio_links = is_array($links) && $links !== []
                ? array_values(array_map(fn ($l) => [
                    'label' => trim((string) ($l['label'] ?? '')),
                    'url' => trim((string) ($l['url'] ?? '')),
                ], $links))
                : null;
        }
        // Auto-generate a unique store slug from the company name when enabling storefront/bio without one.
        if (($company->storefront_enabled || $company->link_in_bio_enabled) && empty($company->store_slug)) {
            $company->store_slug = $this->generateUniqueStoreSlug($company);
        }
        $company->save();

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
        if (array_key_exists('aiModelMode', $companyValidated)) {
            $plan = PlanLimitService::getCurrentPlanSlug($company);
            if (! PlanLimitService::isAiModelModeAllowed($plan, $companyValidated['aiModelMode'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your plan does not allow this AI model selection. Upgrade to Professional or Enterprise.',
                    'code' => 'plan_ai_model_restricted',
                ], 422);
            }
            $settings->ai_model_mode = $companyValidated['aiModelMode'];
            if ($companyValidated['aiModelMode'] !== 'specific') {
                $settings->ai_model_id = null;
            }
        }
        if (array_key_exists('aiModelId', $companyValidated) && ($settings->ai_model_mode ?? 'auto') === 'specific') {
            $settings->ai_model_id = $companyValidated['aiModelId'];
        }
        if (array_key_exists('aiReplyMode', $companyValidated)) {
            $settings->ai_reply_mode = $companyValidated['aiReplyMode'];
        }
        if (array_key_exists('aiCredentialMode', $companyValidated)) {
            $plan = PlanLimitService::getCurrentPlanSlug($company);
            if (! PlanLimitService::isCredentialModeAllowed($plan, $companyValidated['aiCredentialMode'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bring-your-own API keys require Professional or Enterprise.',
                    'code' => 'plan_byok_restricted',
                ], 422);
            }
            $settings->ai_credential_mode = $companyValidated['aiCredentialMode'];
        }
        if (array_key_exists('defaultReplyLanguage', $companyValidated)) {
            $settings->default_reply_language = $companyValidated['defaultReplyLanguage'];
        }
        if (array_key_exists('replyInCustomerLanguage', $companyValidated)) {
            $settings->reply_in_customer_language = $companyValidated['replyInCustomerLanguage'];
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
            $learningConfig = app(AiLearningConfig::class);
            if ($learningConfig->isLearningEnabled()
                && ($learningConfig->all()['allowCompanyOverride'] ?? true)) {
                $settings->learn_from_conversations = $companyValidated['learnFromConversations'];
            }
        }
        if (array_key_exists('autoReplyEnabled', $companyValidated)) {
            $settings->auto_reply_enabled = $companyValidated['autoReplyEnabled'];
        }
        if (array_key_exists('agentCommerceEnabled', $companyValidated)) {
            $wantAgent = (bool) $companyValidated['agentCommerceEnabled'];
            if ($wantAgent && ! CommerceAgentReplyService::isEntitledForCompany($company)) {
                return response()->json([
                    'message' => 'Your plan does not include the conversational AI OS. Upgrade to enable it.',
                ], 422);
            }
            $settings->agent_commerce_enabled = $wantAgent;
        }
        if (array_key_exists('agentProactiveEnabled', $companyValidated)) {
            $settings->agent_proactive_enabled = $companyValidated['agentProactiveEnabled'];
        }
        if (array_key_exists('agentVoiceReplyEnabled', $companyValidated)) {
            $settings->agent_voice_reply_enabled = $companyValidated['agentVoiceReplyEnabled'];
        }
        if (array_key_exists('agentMorningBriefWhatsappEnabled', $companyValidated)) {
            $settings->agent_morning_brief_whatsapp_enabled = $companyValidated['agentMorningBriefWhatsappEnabled'];
        }
        if (array_key_exists('ownerWhatsappPhone', $companyValidated)) {
            $phone = $companyValidated['ownerWhatsappPhone'];
            $settings->owner_whatsapp_phone = is_string($phone) && trim($phone) !== '' ? trim($phone) : null;
        }
        if (array_key_exists('agentBusinessGoals', $companyValidated)) {
            $catalog = array_keys(app(BusinessGoalService::class)->catalog());
            $goals = is_array($companyValidated['agentBusinessGoals'])
                ? array_values(array_intersect($companyValidated['agentBusinessGoals'], $catalog))
                : null;
            $settings->agent_business_goals = $goals;
        }
        if (array_key_exists('businessDna', $companyValidated)) {
            $dna = $companyValidated['businessDna'];
            if ($dna === null || $dna === []) {
                $settings->business_dna = null;
            } else {
                $settings->business_dna = array_filter([
                    'tone' => isset($dna['tone']) ? trim((string) $dna['tone']) : null,
                    'values' => isset($dna['values']) && is_array($dna['values'])
                        ? array_values(array_filter(array_map('trim', $dna['values'])))
                        : null,
                    'risk_tolerance' => $dna['risk_tolerance'] ?? null,
                    'service_philosophy' => isset($dna['service_philosophy'])
                        ? trim((string) $dna['service_philosophy']) : null,
                    'escalation_culture' => isset($dna['escalation_culture'])
                        ? trim((string) $dna['escalation_culture']) : null,
                    'communication_style' => isset($dna['communication_style'])
                        ? trim((string) $dna['communication_style']) : null,
                ], fn ($v) => $v !== null && $v !== []);
            }
        }
        if (array_key_exists('agentCouncilEnabled', $companyValidated)) {
            $settings->agent_council_enabled = $companyValidated['agentCouncilEnabled'];
        }
        if (array_key_exists('devModeEnabled', $companyValidated)) {
            $settings->dev_mode_enabled = (bool) $companyValidated['devModeEnabled'];
        }
        if (array_key_exists('digitalTwin', $companyValidated)) {
            $twin = $companyValidated['digitalTwin'];
            if ($twin === null || $twin === []) {
                $settings->digital_twin = null;
            } else {
                $settings->digital_twin = array_filter([
                    'mission' => isset($twin['mission']) ? trim((string) $twin['mission']) : null,
                    'brand_voice' => isset($twin['brand_voice']) ? trim((string) $twin['brand_voice']) : null,
                    'sales_strategy' => isset($twin['sales_strategy']) ? trim((string) $twin['sales_strategy']) : null,
                    'pricing_rules' => isset($twin['pricing_rules']) ? trim((string) $twin['pricing_rules']) : null,
                    'competitors' => isset($twin['competitors']) ? trim((string) $twin['competitors']) : null,
                    'target_customers' => isset($twin['target_customers']) ? trim((string) $twin['target_customers']) : null,
                ], fn ($v) => $v !== null && $v !== '');
            }
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
        if (array_key_exists('ordersAcceptPesapal', $companyValidated)) {
            $settings->orders_accept_pesapal = $companyValidated['ordersAcceptPesapal'];
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
            $settings->display_currency = strlen($code) === 3 ? $code : 'KES';
        }
        if (array_key_exists('currencySymbol', $companyValidated)) {
            $symbol = $companyValidated['currencySymbol'];
            $settings->currency_symbol = is_string($symbol) && trim($symbol) !== ''
                ? mb_substr(trim($symbol), 0, 16)
                : null;
        }
        if (array_key_exists('thousandsSeparator', $companyValidated)) {
            $thousands = MoneyFormatter::normalizeThousands($companyValidated['thousandsSeparator'] ?? null);
            $settings->thousands_separator = $thousands;
            // Keep decimal paired unless the client also sent an explicit decimal separator.
            if (! array_key_exists('decimalSeparator', $companyValidated)) {
                $settings->decimal_separator = MoneyFormatter::pairedDecimalForThousands($thousands);
            }
        }
        if (array_key_exists('decimalSeparator', $companyValidated)) {
            $thousands = MoneyFormatter::normalizeThousands(
                $settings->thousands_separator ?? ($companyValidated['thousandsSeparator'] ?? null)
            );
            $settings->decimal_separator = MoneyFormatter::normalizeDecimal(
                $companyValidated['decimalSeparator'] ?? null,
                $thousands
            );
            $settings->thousands_separator = $thousands;
        }
        if (array_key_exists('taxEnabled', $companyValidated)) {
            $settings->tax_enabled = (bool) $companyValidated['taxEnabled'];
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
                        $currency = (string) ($existing['currency'] ?? 'kes');
                    }
                    $env = in_array($v['env'] ?? null, ['sandbox', 'production'], true)
                        ? $v['env']
                        : ($existing['env'] ?? 'sandbox');
                    $settings->order_payment_stripe_config = [
                        'secret' => $secret,
                        'currency' => $currency !== '' ? $currency : 'kes',
                        'env' => $env,
                    ];
                } else {
                    $settings->order_payment_stripe_config = null;
                }
            }
        }
        if (array_key_exists('orderPaymentPaystackConfig', $companyValidated)) {
            $v = $companyValidated['orderPaymentPaystackConfig'];
            if ($v === null) {
                $settings->order_payment_paystack_config = null;
            } elseif (is_array($v)) {
                $existing = $settings->order_payment_paystack_config ?? [];
                $secretKey = isset($v['secret_key']) ? trim((string) $v['secret_key']) : '';
                if ($secretKey === '' || $this->isMaskedSecretInput($secretKey)) {
                    $secretKey = (string) ($existing['secret_key'] ?? '');
                }
                $publicKey = isset($v['public_key']) ? trim((string) $v['public_key']) : '';
                if ($publicKey === '') {
                    $publicKey = (string) ($existing['public_key'] ?? '');
                }
                $currency = isset($v['currency']) ? trim((string) $v['currency']) : '';
                if ($currency === '') {
                    $currency = (string) ($existing['currency'] ?? 'kes');
                }
                $env = in_array($v['env'] ?? null, ['sandbox', 'production'], true)
                    ? $v['env']
                    : ($existing['env'] ?? 'sandbox');
                if ($secretKey !== '' || $publicKey !== '') {
                    $settings->order_payment_paystack_config = [
                        'secret_key' => $secretKey,
                        'public_key' => $publicKey,
                        'currency' => $currency !== '' ? $currency : 'kes',
                        'env' => $env,
                    ];
                } else {
                    $settings->order_payment_paystack_config = null;
                }
            }
        }
        if (array_key_exists('orderPaymentPesapalConfig', $companyValidated)) {
            $v = $companyValidated['orderPaymentPesapalConfig'];
            if ($v === null) {
                $settings->order_payment_pesapal_config = null;
            } elseif (is_array($v)) {
                $existing = $settings->order_payment_pesapal_config ?? [];
                $consumerKey = isset($v['consumer_key']) ? trim((string) $v['consumer_key']) : '';
                if ($consumerKey === '' || $this->isMaskedSecretInput($consumerKey)) {
                    $consumerKey = (string) ($existing['consumer_key'] ?? '');
                }
                $consumerSecret = isset($v['consumer_secret']) ? trim((string) $v['consumer_secret']) : '';
                if ($consumerSecret === '' || $this->isMaskedSecretInput($consumerSecret)) {
                    $consumerSecret = (string) ($existing['consumer_secret'] ?? '');
                }
                $currency = isset($v['currency']) ? trim((string) $v['currency']) : '';
                if ($currency === '') {
                    $currency = (string) ($existing['currency'] ?? 'kes');
                }
                $env = in_array($v['env'] ?? null, ['sandbox', 'production'], true)
                    ? $v['env']
                    : ($existing['env'] ?? 'sandbox');
                if ($consumerKey !== '' && $consumerSecret !== '') {
                    $settings->order_payment_pesapal_config = [
                        'consumer_key' => $consumerKey,
                        'consumer_secret' => $consumerSecret,
                        'currency' => $currency !== '' ? $currency : 'kes',
                        'env' => $env,
                    ];
                } else {
                    $settings->order_payment_pesapal_config = null;
                }
            }
        }
        if (array_key_exists('ordersAcceptCod', $companyValidated)) {
            $settings->orders_accept_cod = $companyValidated['ordersAcceptCod'];
        }
        if (array_key_exists('deliveryFeesEnabled', $companyValidated)) {
            $settings->delivery_fees_enabled = $companyValidated['deliveryFeesEnabled'];
        }
        if (array_key_exists('defaultDeliveryFee', $companyValidated)) {
            $settings->default_delivery_fee = $companyValidated['defaultDeliveryFee'] ?? 0;
        }
        if (array_key_exists('freeDeliveryAbove', $companyValidated)) {
            $settings->free_delivery_above = $companyValidated['freeDeliveryAbove'];
        }
        if (array_key_exists('dineInEnabled', $companyValidated)) {
            $settings->dine_in_enabled = $companyValidated['dineInEnabled'];
        }
        if (array_key_exists('paymentRecoveryEnabled', $companyValidated)) {
            $settings->payment_recovery_enabled = $companyValidated['paymentRecoveryEnabled'];
        }
        if (array_key_exists('paymentRecoveryHours', $companyValidated)) {
            $hours = $companyValidated['paymentRecoveryHours'];
            $settings->payment_recovery_hours = is_array($hours) && $hours !== []
                ? array_values(array_unique(array_map('intval', $hours)))
                : null;
        }
        if (array_key_exists('abandonedCartRecoveryEnabled', $companyValidated)) {
            $settings->abandoned_cart_recovery_enabled = (bool) $companyValidated['abandonedCartRecoveryEnabled'];
        }
        if (array_key_exists('storefrontWhatsappOrderNotify', $companyValidated)) {
            $settings->storefront_whatsapp_order_notify = (bool) $companyValidated['storefrontWhatsappOrderNotify'];
        }
        if (array_key_exists('abandonedCartTemplateName', $companyValidated)) {
            $v = $companyValidated['abandonedCartTemplateName'];
            $settings->abandoned_cart_template_name = (is_string($v) && trim($v) !== '') ? trim($v) : null;
        }
        if (array_key_exists('birthdayAutomationEnabled', $companyValidated)) {
            $settings->birthday_automation_enabled = $companyValidated['birthdayAutomationEnabled'];
        }
        if (array_key_exists('birthdayCouponPercent', $companyValidated)) {
            $settings->birthday_coupon_percent = $companyValidated['birthdayCouponPercent'] ?? 10;
        }
        if (array_key_exists('birthdayMessageTemplate', $companyValidated)) {
            $v = $companyValidated['birthdayMessageTemplate'];
            $settings->birthday_message_template = (is_string($v) && trim($v) !== '') ? trim($v) : null;
        }
        if (array_key_exists('winbackAutomationEnabled', $companyValidated)) {
            $settings->winback_automation_enabled = $companyValidated['winbackAutomationEnabled'];
        }
        if (array_key_exists('winbackDaysInactive', $companyValidated)) {
            $settings->winback_days_inactive = $companyValidated['winbackDaysInactive'] ?? 30;
        }
        if (array_key_exists('spamOrderProtectionEnabled', $companyValidated)) {
            $settings->spam_order_protection_enabled = $companyValidated['spamOrderProtectionEnabled'];
        }
        if (array_key_exists('spamMaxOrdersPerHour', $companyValidated)) {
            $settings->spam_max_orders_per_hour = $companyValidated['spamMaxOrdersPerHour'] ?? 5;
        }
        if (array_key_exists('spamMaxOrdersPerDay', $companyValidated)) {
            $settings->spam_max_orders_per_day = $companyValidated['spamMaxOrdersPerDay'] ?? 20;
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

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    protected function maskOrderPaymentPaystackConfig(?array $config): ?array
    {
        if ($config === null || $config === []) {
            return null;
        }
        $out = $config;
        if (! empty($out['secret_key']) && is_string($out['secret_key'])) {
            $out['secret_key'] = $this->maskSecretString($out['secret_key']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    protected function maskOrderPaymentPesapalConfig(?array $config): ?array
    {
        if ($config === null || $config === []) {
            return null;
        }
        $out = $config;
        if (! empty($out['consumer_secret']) && is_string($out['consumer_secret'])) {
            $out['consumer_secret'] = $this->maskSecretString($out['consumer_secret']);
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

    /** Generate a unique, URL-safe store slug from the company name. */
    protected function generateUniqueStoreSlug(Company $company): string
    {
        $base = Str::slug($company->name) ?: 'store';
        $slug = $base;
        $suffix = 1;
        while (Company::where('store_slug', $slug)->where('id', '!=', $company->id)->exists()) {
            $suffix++;
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
