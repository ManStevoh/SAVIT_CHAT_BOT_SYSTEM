<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecaptchaService
{
    public function isEnabled(): bool
    {
        $settings = PlatformSetting::query()->first();
        if (! $settings || ! $settings->recaptcha_enabled) {
            return false;
        }

        return filled($settings->recaptcha_site_key) && filled($settings->getRawOriginal('recaptcha_secret_key'));
    }

    public function siteKey(): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        return PlatformSetting::query()->value('recaptcha_site_key');
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function assertValid(?string $token, ?string $remoteIp = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        if (! filled($token)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recaptchaToken' => ['Please complete the captcha challenge.'],
            ]);
        }

        if (! $this->verify($token, $remoteIp)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'recaptchaToken' => ['Captcha verification failed. Please try again.'],
            ]);
        }
    }

    public function verify(string $token, ?string $remoteIp = null): bool
    {
        $settings = PlatformSetting::query()->first();
        $secret = $settings?->getRawOriginal('recaptcha_secret_key');
        if (! filled($secret)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $remoteIp,
                ]));

            if (! $response->ok()) {
                Log::warning('reCAPTCHA siteverify HTTP failure', ['status' => $response->status()]);

                return false;
            }

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            Log::warning('reCAPTCHA verification error', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
