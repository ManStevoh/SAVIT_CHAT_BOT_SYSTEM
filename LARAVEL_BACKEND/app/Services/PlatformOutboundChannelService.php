<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WhatsAppAccount;

class PlatformOutboundChannelService
{
    public function rememberOwnerPhone(User $user): void
    {
        $company = $user->company;
        if (! $company) {
            return;
        }

        $phone = trim((string) ($user->phone ?: $company->phone ?: ''));
        if ($phone === '') {
            return;
        }

        $settings = CompanySetting::firstOrCreate(['company_id' => $company->id]);
        if (! trim((string) ($settings->owner_whatsapp_phone ?? ''))) {
            $settings->owner_whatsapp_phone = $phone;
            $settings->save();
        }
    }

    public function recipientPhone(User $user): ?string
    {
        $company = $user->company;
        $raw = $company?->settings?->owner_whatsapp_phone
            ?: $user->phone
            ?: $company?->phone;
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if (strlen($digits) < 9) {
            return null;
        }

        return $digits;
    }

    public function whatsappAccount(): ?WhatsAppAccount
    {
        $settings = PlatformSetting::first();
        if ($settings && $settings->lifecycle_whatsapp_enabled === false) {
            return null;
        }

        $phoneNumberId = trim((string) ($settings?->lifecycle_whatsapp_phone_number_id ?: config('merchant_lifecycle.whatsapp.phone_number_id') ?: ''));
        $token = trim((string) ($settings?->lifecycle_whatsapp_access_token ?: config('merchant_lifecycle.whatsapp.access_token') ?: ''));
        if ($phoneNumberId === '' || $token === '') {
            return null;
        }

        $account = new WhatsAppAccount;
        $account->phone_number_id = $phoneNumberId;
        $account->status = 'active';
        $account->access_token = $token;

        return $account;
    }

    public function templateLanguage(): string
    {
        $settings = PlatformSetting::first();
        $lang = trim((string) ($settings?->lifecycle_whatsapp_template_lang ?: config('merchant_lifecycle.whatsapp.template_language') ?: 'en'));

        return $lang !== '' ? $lang : 'en';
    }

    /**
     * @param  list<string>  $templateParams
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendWhatsApp(
        WhatsAppMessageSenderService $whatsapp,
        WhatsAppAccount $account,
        string $to,
        string $text,
        string $ctaLabel,
        string $ctaUrl,
        string $template = '',
        array $templateParams = [],
    ): array {
        $lang = $this->templateLanguage();
        if ($template !== '') {
            $tpl = $whatsapp->sendTemplate($account, $to, $template, $lang, $templateParams);
            if ($tpl['success'] ?? false) {
                return $tpl;
            }
        }

        if ($ctaUrl !== '') {
            $cta = $whatsapp->sendInteractiveCtaUrl(
                $account,
                $to,
                $text,
                $ctaLabel !== '' ? $ctaLabel : 'Open',
                $ctaUrl,
            );
            if ($cta['success'] ?? false) {
                return $cta;
            }
        }

        return $whatsapp->sendText($account, $to, $ctaUrl !== '' ? $text."\n\n".$ctaUrl : $text);
    }
}
