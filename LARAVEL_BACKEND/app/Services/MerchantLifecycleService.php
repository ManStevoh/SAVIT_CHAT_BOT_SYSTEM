<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\MerchantLifecycleSend;
use App\Models\PlatformSetting;
use App\Models\Product;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class MerchantLifecycleService
{
    public const STEP_WELCOME = 'welcome';

    public const STEP_VERIFIED = 'verified';

    public const STEP_ADD_FIRST_PRODUCT = 'add_first_product';

    public const STEP_CONNECT_WHATSAPP = 'connect_whatsapp';

    public const STEP_ENABLE_PAYMENTS = 'enable_payments';

    public const STEP_SHARE_STORE = 'share_store';

    public const STEP_MARKETING_GROWTH = 'marketing_growth';

    public const STEP_MARKETING_TIPS = 'marketing_tips';

    public function __construct(
        protected MailService $mail,
        protected WhatsAppMessageSenderService $whatsapp,
        protected CompanySetupStatusService $setup,
    ) {}

    /**
     * @param  array{days?: int, plan_name?: string, end_date?: string, is_free?: bool}|null  $trial
     */
    public function onRegistered(User $user, bool $sendWelcomeEmail = false, ?array $trial = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $user->loadMissing('company.settings');
        $this->rememberOwnerPhone($user);

        if ($sendWelcomeEmail) {
            $this->sendWelcomeEmail($user, $trial);
        } else {
            $this->record($user, self::STEP_WELCOME, 'email', MerchantLifecycleSend::STATUS_SENT);
        }

        $this->deliver($user, self::STEP_WELCOME, email: false, whatsapp: true);
    }

    public function onVerified(User $user): void
    {
        if (! $this->enabled()) {
            return;
        }

        $user->loadMissing('company.settings');
        $this->rememberOwnerPhone($user);
        $this->deliver($user, self::STEP_VERIFIED, email: true, whatsapp: true);
    }

    /**
     * @return array{processed: int, sent: int}
     */
    public function processDue(?int $userId = null): array
    {
        if (! $this->enabled()) {
            return ['processed' => 0, 'sent' => 0];
        }

        $query = User::query()
            ->whereIn('role', ['company_owner', 'company_admin'])
            ->whereNotNull('company_id')
            ->where('status', 'active')
            ->whereNotNull('email_verified_at')
            ->with(['company.settings']);

        if ($userId) {
            $query->where('id', $userId);
        }

        $sent = 0;
        $processed = 0;
        foreach ($query->cursor() as $user) {
            $processed++;
            $sent += $this->processUser($user);
        }

        return ['processed' => $processed, 'sent' => $sent];
    }

    public function unsubscribe(User $user): void
    {
        $user->update([
            'marketing_consent' => false,
            'marketing_consent_at' => $user->marketing_consent_at ?? now(),
        ]);
    }

    public function unsubscribeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'marketing.unsubscribe',
            now()->addDays(90),
            ['user' => $user->id]
        );
    }

    private function processUser(User $user): int
    {
        $company = $user->company;
        if (! $company || in_array($company->status, ['suspended', 'inactive'], true)) {
            return 0;
        }

        $status = $this->setup->status($company);
        $registeredAt = $user->created_at ?? now();
        $sent = 0;

        if (! $this->already($user, self::STEP_WELCOME, 'whatsapp')) {
            $sent += $this->deliver($user, self::STEP_WELCOME, email: false, whatsapp: true);
        }
        if ($this->already($user, self::STEP_VERIFIED, 'email')
            && ! $this->already($user, self::STEP_VERIFIED, 'whatsapp')) {
            $sent += $this->deliver($user, self::STEP_VERIFIED, email: false, whatsapp: true);
        }

        if (! $this->stepDone($status, 'product')
            && $registeredAt->copy()->addSeconds($this->delay(self::STEP_ADD_FIRST_PRODUCT))->lte(now())) {
            $sent += $this->deliver($user, self::STEP_ADD_FIRST_PRODUCT);
        }

        if ($this->hasStep($status, 'whatsapp')
            && ! $this->stepDone($status, 'whatsapp')
            && $registeredAt->copy()->addSeconds($this->delay(self::STEP_CONNECT_WHATSAPP))->lte(now())) {
            $sent += $this->deliver($user, self::STEP_CONNECT_WHATSAPP);
        }

        if (! $this->stepDone($status, 'payments')
            && $registeredAt->copy()->addSeconds($this->delay(self::STEP_ENABLE_PAYMENTS))->lte(now())) {
            $sent += $this->deliver($user, self::STEP_ENABLE_PAYMENTS);
        }

        $hasProduct = Product::query()->where('company_id', $company->id)->exists();
        $storefrontReady = $this->stepDone($status, 'storefront');
        $shared = $this->stepDone($status, 'share');
        if ($hasProduct && $storefrontReady && ! $shared && $this->hasStep($status, 'share')
            && $registeredAt->copy()->addSeconds($this->delay(self::STEP_SHARE_STORE))->lte(now())) {
            $sent += $this->deliver($user, self::STEP_SHARE_STORE);
        }

        if ($user->marketing_consent) {
            if ($registeredAt->copy()->addSeconds($this->delay(self::STEP_MARKETING_GROWTH))->lte(now())) {
                $sent += $this->deliver($user, self::STEP_MARKETING_GROWTH);
            }
            if ($registeredAt->copy()->addSeconds($this->delay(self::STEP_MARKETING_TIPS))->lte(now())) {
                $sent += $this->deliver($user, self::STEP_MARKETING_TIPS);
            }
        }

        return $sent;
    }

    private function deliver(User $user, string $step, bool $email = true, bool $whatsapp = true): int
    {
        $copy = $this->copyFor($user, $step);
        $sent = 0;

        if ($email && $user->email && ! $this->already($user, $step, 'email')) {
            try {
                $html = $copy['html'];
                if ($this->isMarketing($step)) {
                    $html .= '<p style="margin-top:24px;font-size:12px;color:#6b7280;">You opted in to product updates. <a href="'.e($this->unsubscribeUrl($user)).'">Unsubscribe</a>.</p>';
                }
                $this->mail->sendMerchantLifecycleEmail($user->email, $copy['subject'], $html);
                $this->record($user, $step, 'email', MerchantLifecycleSend::STATUS_SENT);
                $sent++;
            } catch (\Throwable $e) {
                $this->record($user, $step, 'email', MerchantLifecycleSend::STATUS_FAILED, $e->getMessage());
                Log::warning('Merchant lifecycle email failed', [
                    'user_id' => $user->id,
                    'step' => $step,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($whatsapp && ! $this->already($user, $step, 'whatsapp')) {
            $to = $this->recipientPhone($user);
            $account = $this->platformWhatsAppAccount();
            if ($to === null || $account === null) {
                $this->record($user, $step, 'whatsapp', MerchantLifecycleSend::STATUS_SKIPPED, $to === null ? 'no_phone' : 'no_platform_whatsapp');
            } else {
                $result = $this->sendWhatsApp($account, $to, $step, $copy);
                $this->record(
                    $user,
                    $step,
                    'whatsapp',
                    ($result['success'] ?? false) ? MerchantLifecycleSend::STATUS_SENT : MerchantLifecycleSend::STATUS_FAILED,
                    $result['error'] ?? null,
                    ['message_id' => $result['message_id'] ?? null]
                );
                if ($result['success'] ?? false) {
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /**
     * @param  array{whatsapp: string, ctaUrl: string, ctaLabel: string, templateParams: list<string>}  $copy
     * @return array{success: bool, message_id?: string, error?: string}
     */
    private function sendWhatsApp(WhatsAppAccount $account, string $to, string $step, array $copy): array
    {
        $template = (string) (config('merchant_lifecycle.whatsapp.templates.'.$step) ?: '');
        $lang = $this->templateLanguage();

        if ($template !== '') {
            $tpl = $this->whatsapp->sendTemplate(
                $account,
                $to,
                $template,
                $lang,
                $copy['templateParams'],
            );
            if ($tpl['success'] ?? false) {
                return $tpl;
            }
        }

        $cta = $this->whatsapp->sendInteractiveCtaUrl(
            $account,
            $to,
            $copy['whatsapp'],
            $copy['ctaLabel'],
            $copy['ctaUrl'],
        );
        if ($cta['success'] ?? false) {
            return $cta;
        }

        return $this->whatsapp->sendText($account, $to, $copy['whatsapp']."\n\n".$copy['ctaUrl']);
    }

    /**
     * @param  array{days?: int, plan_name?: string, end_date?: string, is_free?: bool}|null  $trial
     */
    private function sendWelcomeEmail(User $user, ?array $trial): void
    {
        if ($this->already($user, self::STEP_WELCOME, 'email') || ! $user->email) {
            return;
        }

        $isRealTrial = ! empty($trial) && empty($trial['is_free']);
        try {
            $this->mail->sendWelcomeTrialEmail(
                $user->email,
                $user->name,
                $trial['plan_name'] ?? 'Starter',
                $trial['days'] ?? (int) config('subscription.default_trial_days', 14),
                $trial['end_date'] ?? now()->addDays(14)->format('F j, Y'),
                $isRealTrial
            );
            $this->record($user, self::STEP_WELCOME, 'email', MerchantLifecycleSend::STATUS_SENT);
        } catch (\Throwable $e) {
            $this->record($user, self::STEP_WELCOME, 'email', MerchantLifecycleSend::STATUS_FAILED, $e->getMessage());
        }
    }

    /**
     * @return array{subject: string, html: string, whatsapp: string, ctaUrl: string, ctaLabel: string, templateParams: list<string>}
     */
    private function copyFor(User $user, string $step): array
    {
        $name = trim((string) $user->name) ?: 'there';
        $app = MailService::applicationName();
        $dashboard = $this->frontend('/dashboard');
        $products = $this->frontend('/dashboard/products');
        $payments = $this->frontend('/dashboard/settings?tab=order-payments');
        $whatsapp = $this->frontend('/dashboard/settings?tab=whatsapp');
        $storefront = $this->frontend('/dashboard/storefront');
        $growth = $this->frontend('/dashboard/growth');
        $company = $user->company;
        $storeUrl = $this->storeUrl($company);
        $btn = fn (string $url, string $label): string => '<p><a href="'.e($url).'" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">'.e($label).'</a></p>';

        return match ($step) {
            self::STEP_WELCOME => [
                'subject' => "[{$app}] Welcome — your shop is ready to set up",
                'html' => '<p>Hi '.e($name).',</p><p>Welcome to <strong>'.e($app).'</strong>. Add a product, switch on payments, and share your shop link.</p>'.$btn($dashboard, 'Open dashboard'),
                'whatsapp' => "Hi {$name}, welcome to {$app}! Your shop is live to set up. Start by adding your first product.",
                'ctaUrl' => $products,
                'ctaLabel' => 'Add a product',
                'templateParams' => [$name, $app],
            ],
            self::STEP_VERIFIED => [
                'subject' => "[{$app}] Email confirmed — add your first product",
                'html' => '<p>Hi '.e($name).',</p><p>Your email is verified. Add one product (name, price, photo) so customers can buy.</p>'.$btn($products, 'Add your first product'),
                'whatsapp' => "Hi {$name}, your {$app} email is confirmed. Add your first product so the shop can take orders.",
                'ctaUrl' => $products,
                'ctaLabel' => 'Add product',
                'templateParams' => [$name],
            ],
            self::STEP_ADD_FIRST_PRODUCT => [
                'subject' => "[{$app}] Add your first product",
                'html' => '<p>Hi '.e($name).',</p><p>Your shop still has no products. Add one item — name, price, and a photo — and you can start selling.</p>'.$btn($products, 'Add your first product'),
                'whatsapp' => "Hi {$name}, your {$app} shop has no products yet. Add one item (name, price, photo) to start selling.",
                'ctaUrl' => $products,
                'ctaLabel' => 'Add product',
                'templateParams' => [$name, $products],
            ],
            self::STEP_CONNECT_WHATSAPP => [
                'subject' => "[{$app}] Connect WhatsApp to take chats and orders",
                'html' => '<p>Hi '.e($name).',</p><p>Connect WhatsApp so customers can message you and the AI can reply from your number.</p>'.$btn($whatsapp, 'Connect WhatsApp'),
                'whatsapp' => "Hi {$name}, connect WhatsApp in {$app} so customer chats and orders land in your inbox.",
                'ctaUrl' => $whatsapp,
                'ctaLabel' => 'Connect WhatsApp',
                'templateParams' => [$name],
            ],
            self::STEP_ENABLE_PAYMENTS => [
                'subject' => "[{$app}] Switch on a payout method",
                'html' => '<p>Hi '.e($name).',</p><p>No payout method is on yet. Enable M-Pesa, Paystack, or cash on delivery so paid orders can complete.</p>'.$btn($payments, 'Open payments'),
                'whatsapp' => "Hi {$name}, switch on a payout method in {$app} (M-Pesa, Paystack, or cash) so you can collect payment.",
                'ctaUrl' => $payments,
                'ctaLabel' => 'Open payments',
                'templateParams' => [$name],
            ],
            self::STEP_SHARE_STORE => [
                'subject' => "[{$app}] Share your shop link",
                'html' => '<p>Hi '.e($name).',</p><p>Your store is ready. Send this link to customers on WhatsApp, Instagram, or SMS:</p><p><strong>'.e($storeUrl ?: $storefront).'</strong></p>'.$btn($storeUrl ?: $storefront, 'Open your shop link'),
                'whatsapp' => "Hi {$name}, share your {$app} shop with customers: ".($storeUrl ?: $storefront),
                'ctaUrl' => $storeUrl ?: $storefront,
                'ctaLabel' => 'Open shop',
                'templateParams' => [$name, $storeUrl ?: $storefront],
            ],
            self::STEP_MARKETING_GROWTH => [
                'subject' => "[{$app}] Grow faster — campaigns and ads from your dashboard",
                'html' => '<p>Hi '.e($name).',</p><p>You opted in to product updates. Growth Engine can draft posts, run ads, and follow up cold chats so more shoppers convert.</p>'.$btn($growth, 'Open Growth Engine'),
                'whatsapp' => "Hi {$name}, {$app} Growth Engine can draft posts and follow up shoppers. Open it in your dashboard.",
                'ctaUrl' => $growth,
                'ctaLabel' => 'Open Growth',
                'templateParams' => [$name],
            ],
            self::STEP_MARKETING_TIPS => [
                'subject' => "[{$app}] Tip: share your shop link every day this week",
                'html' => '<p>Hi '.e($name).',</p><p>Shops that send their link on WhatsApp status and Instagram bio see more first orders. Here is yours:</p><p><strong>'.e($storeUrl ?: $storefront).'</strong></p>'.$btn($storefront, 'Copy your store link'),
                'whatsapp' => "Hi {$name}, a {$app} tip: put your shop link on WhatsApp status and Instagram. ".($storeUrl ?: $storefront),
                'ctaUrl' => $storeUrl ?: $storefront,
                'ctaLabel' => 'Open shop',
                'templateParams' => [$name, $storeUrl ?: $storefront],
            ],
            default => [
                'subject' => "[{$app}] A quick update",
                'html' => '<p>Hi '.e($name).',</p><p>Open your dashboard to continue setup.</p>'.$btn($dashboard, 'Open dashboard'),
                'whatsapp' => "Hi {$name}, continue setup in {$app}.",
                'ctaUrl' => $dashboard,
                'ctaLabel' => 'Open dashboard',
                'templateParams' => [$name],
            ],
        };
    }

    private function rememberOwnerPhone(User $user): void
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

    private function recipientPhone(User $user): ?string
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

    private function platformWhatsAppAccount(): ?WhatsAppAccount
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

    private function templateLanguage(): string
    {
        $settings = PlatformSetting::first();
        $lang = trim((string) ($settings?->lifecycle_whatsapp_template_lang ?: config('merchant_lifecycle.whatsapp.template_language') ?: 'en'));

        return $lang !== '' ? $lang : 'en';
    }

    private function already(User $user, string $step, string $channel): bool
    {
        return MerchantLifecycleSend::query()
            ->where('user_id', $user->id)
            ->where('step', $step)
            ->where('channel', $channel)
            ->where('status', MerchantLifecycleSend::STATUS_SENT)
            ->exists();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function record(User $user, string $step, string $channel, string $status, ?string $error = null, ?array $payload = null): void
    {
        MerchantLifecycleSend::updateOrCreate(
            [
                'user_id' => $user->id,
                'step' => $step,
                'channel' => $channel,
            ],
            [
                'company_id' => $user->company_id,
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 500) : null,
                'payload' => $payload,
                'sent_at' => $status === MerchantLifecycleSend::STATUS_SENT ? now() : null,
            ]
        );
    }

    private function delay(string $step): int
    {
        return max(0, (int) config('merchant_lifecycle.delays.'.$step, 0));
    }

    /**
     * @param  array{steps: list<array{id: string, done: bool}>}  $status
     */
    private function stepDone(array $status, string $id): bool
    {
        foreach ($status['steps'] as $step) {
            if (($step['id'] ?? '') === $id) {
                return (bool) ($step['done'] ?? false);
            }
        }

        return false;
    }

    /**
     * @param  array{steps: list<array{id: string, done?: bool}>}  $status
     */
    private function hasStep(array $status, string $id): bool
    {
        foreach ($status['steps'] as $step) {
            if (($step['id'] ?? '') === $id) {
                return true;
            }
        }

        return false;
    }

    private function enabled(): bool
    {
        return (bool) config('merchant_lifecycle.enabled', true);
    }

    private function isMarketing(string $step): bool
    {
        return str_starts_with($step, 'marketing_');
    }

    private function frontend(string $path): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').$path;
    }

    private function storeUrl(?Company $company): ?string
    {
        if (! $company || ! is_string($company->store_slug) || trim($company->store_slug) === '') {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/s/'.trim($company->store_slug);
    }
}
