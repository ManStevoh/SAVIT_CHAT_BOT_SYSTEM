<?php

namespace App\Services;

use App\Models\PlatformMarketingMessage;
use App\Models\PlatformMarketingSend;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

class PlatformMarketingService
{
    public function __construct(
        protected MailService $mail,
        protected WhatsAppMessageSenderService $whatsapp,
        protected PlatformOutboundChannelService $channels,
    ) {}

    /**
     * @return array{processed: int, sent: int}
     */
    public function processDue(): array
    {
        $sent = 0;
        $processed = 0;

        $messages = PlatformMarketingMessage::query()
            ->whereIn('status', [
                PlatformMarketingMessage::STATUS_ACTIVE,
                PlatformMarketingMessage::STATUS_SCHEDULED,
            ])
            ->get();

        foreach ($messages as $message) {
            $processed++;
            $sent += $this->processMessage($message);
        }

        return ['processed' => $processed, 'sent' => $sent];
    }

    public function sendNow(PlatformMarketingMessage $message): int
    {
        if ($message->status === PlatformMarketingMessage::STATUS_DRAFT) {
            $message->status = PlatformMarketingMessage::STATUS_ACTIVE;
            $message->save();
        }

        $count = $this->broadcast($message, emailWhatsapp: true);
        $message->last_run_at = now();
        $message->save();

        return $count;
    }

    public function onRegistered(User $user): void
    {
        $this->fireTrigger($user, 'registered');
    }

    public function onVerified(User $user): void
    {
        $this->fireTrigger($user, 'verified');
    }

    public function onLogin(User $user): void
    {
        $this->fireTrigger($user, 'login');
    }

    /**
     * @return list<array{id: int, title: string, body: string, ctaLabel: ?string, ctaUrl: ?string}>
     */
    public function pendingPopups(User $user): array
    {
        if (! $this->isMerchant($user)) {
            return [];
        }

        $user->loadMissing('company.settings');
        $out = [];

        $messages = PlatformMarketingMessage::query()
            ->where('channel_popup', true)
            ->whereIn('status', [
                PlatformMarketingMessage::STATUS_ACTIVE,
                PlatformMarketingMessage::STATUS_SCHEDULED,
            ])
            ->orderByDesc('id')
            ->get();

        foreach ($messages as $message) {
            if (! $message->isLive()) {
                continue;
            }
            if (! $this->userInAudience($user, $message)) {
                continue;
            }
            if ($this->popupClosed($user, $message)) {
                continue;
            }
            $out[] = [
                'id' => $message->id,
                'title' => $message->title,
                'body' => $message->body_text,
                'ctaLabel' => $message->cta_label,
                'ctaUrl' => $message->cta_url,
            ];
        }

        return $out;
    }

    public function dismissPopup(User $user, PlatformMarketingMessage $message): void
    {
        $this->record(
            $message,
            $user,
            'popup',
            $this->periodKey($message),
            PlatformMarketingSend::STATUS_DISMISSED,
        );
    }

    public function audienceCount(PlatformMarketingMessage $message): int
    {
        return $this->audienceQuery($message)->count();
    }

    private function processMessage(PlatformMarketingMessage $message): int
    {
        $sent = 0;

        if ($message->send_mode === PlatformMarketingMessage::MODE_SCHEDULED
            && $message->scheduled_at
            && $message->scheduled_at->lte(now())
            && $message->last_run_at === null) {
            $sent += $this->broadcast($message, emailWhatsapp: true);
            $message->status = PlatformMarketingMessage::STATUS_ACTIVE;
            $message->last_run_at = now();
            $message->save();

            return $sent;
        }

        if ($message->send_mode === PlatformMarketingMessage::MODE_RECURRING
            && $message->status === PlatformMarketingMessage::STATUS_ACTIVE) {
            $sent += $this->broadcast($message, emailWhatsapp: true);
            $message->last_run_at = now();
            $message->save();

            return $sent;
        }

        if ($message->send_mode === PlatformMarketingMessage::MODE_TRIGGER
            && $message->status === PlatformMarketingMessage::STATUS_ACTIVE
            && in_array($message->trigger, ['registered', 'verified', 'login'], true)) {
            foreach ($this->audienceQuery($message)->cursor() as $user) {
                if ($this->triggerReady($user, $message)) {
                    $sent += $this->deliver($message, $user, emailWhatsapp: true);
                }
            }
        }

        return $sent;
    }

    private function fireTrigger(User $user, string $trigger): void
    {
        if (! $this->isMerchant($user)) {
            return;
        }

        $user->loadMissing('company.settings');
        $this->channels->rememberOwnerPhone($user);

        $messages = PlatformMarketingMessage::query()
            ->where('status', PlatformMarketingMessage::STATUS_ACTIVE)
            ->where('send_mode', PlatformMarketingMessage::MODE_TRIGGER)
            ->where('trigger', $trigger)
            ->get();

        foreach ($messages as $message) {
            if (! $this->userInAudience($user, $message)) {
                continue;
            }
            if (! $this->triggerReady($user, $message)) {
                continue;
            }
            $this->deliver($message, $user, emailWhatsapp: true);
        }
    }

    private function triggerReady(User $user, PlatformMarketingMessage $message): bool
    {
        $delay = max(0, (int) $message->trigger_delay_seconds);
        $anchor = match ($message->trigger) {
            'verified' => $user->email_verified_at ?? $user->created_at,
            'login' => $user->last_login_at ?? $user->created_at,
            default => $user->created_at,
        };
        if (! $anchor) {
            return false;
        }

        return $anchor->copy()->addSeconds($delay)->lte(now());
    }

    private function broadcast(PlatformMarketingMessage $message, bool $emailWhatsapp): int
    {
        $sent = 0;
        foreach ($this->audienceQuery($message)->cursor() as $user) {
            $sent += $this->deliver($message, $user, $emailWhatsapp);
        }

        return $sent;
    }

    private function deliver(PlatformMarketingMessage $message, User $user, bool $emailWhatsapp): int
    {
        $user->loadMissing('company.settings');
        $this->channels->rememberOwnerPhone($user);
        $period = $this->periodKey($message);
        $sent = 0;

        if ($emailWhatsapp && $message->channel_email && $user->email) {
            if (! $user->marketing_consent) {
                $this->record($message, $user, 'email', $period, PlatformMarketingSend::STATUS_SKIPPED, 'no_marketing_consent');
            } elseif ($this->already($message, $user, 'email', $period)) {
                // already sent this period
            } else {
                try {
                    $html = $this->emailHtml($message, $user);
                    $this->mail->sendMerchantLifecycleEmail($user->email, $this->emailSubject($message), $html);
                    $this->record($message, $user, 'email', $period, PlatformMarketingSend::STATUS_SENT);
                    $sent++;
                } catch (\Throwable $e) {
                    $this->record($message, $user, 'email', $period, PlatformMarketingSend::STATUS_FAILED, $e->getMessage());
                    Log::warning('Platform marketing email failed', [
                        'message_id' => $message->id,
                        'user_id' => $user->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if ($emailWhatsapp && $message->channel_whatsapp) {
            if (! $user->marketing_consent) {
                $this->record($message, $user, 'whatsapp', $period, PlatformMarketingSend::STATUS_SKIPPED, 'no_marketing_consent');
            } elseif ($this->already($message, $user, 'whatsapp', $period)) {
                // already sent
            } else {
                $to = $this->channels->recipientPhone($user);
                $account = $this->channels->whatsappAccount();
                if ($to === null || $account === null) {
                    $this->record(
                        $message,
                        $user,
                        'whatsapp',
                        $period,
                        PlatformMarketingSend::STATUS_SKIPPED,
                        $to === null ? 'no_phone' : 'no_platform_whatsapp'
                    );
                } else {
                    $result = $this->channels->sendWhatsApp(
                        $this->whatsapp,
                        $account,
                        $to,
                        $this->whatsappText($message, $user),
                        (string) ($message->cta_label ?: 'Open'),
                        (string) ($message->cta_url ?: ''),
                        (string) config('merchant_lifecycle.whatsapp.templates.marketing_growth', ''),
                        [trim((string) $user->name) ?: 'there', MailService::applicationName()],
                    );
                    $ok = (bool) ($result['success'] ?? false);
                    $this->record(
                        $message,
                        $user,
                        'whatsapp',
                        $period,
                        $ok ? PlatformMarketingSend::STATUS_SENT : PlatformMarketingSend::STATUS_FAILED,
                        $result['error'] ?? null,
                        ['message_id' => $result['message_id'] ?? null]
                    );
                    if ($ok) {
                        $sent++;
                    }
                }
            }
        }

        return $sent;
    }

    private function audienceQuery(PlatformMarketingMessage $message): Builder
    {
        $query = User::query()
            ->whereIn('role', ['company_owner', 'company_admin'])
            ->where('status', 'active')
            ->whereNotNull('company_id')
            ->with(['company.settings']);

        return match ($message->audience) {
            'marketing_consent' => $query->where('marketing_consent', true),
            'no_product' => $query->whereDoesntHave('company.products'),
            'no_payments' => $query->whereHas('company.settings', function (Builder $q) {
                $q->where('orders_accept_mpesa', false)
                    ->where('orders_accept_stripe', false)
                    ->where('orders_accept_paystack', false)
                    ->where('orders_accept_pesapal', false)
                    ->where('orders_accept_flutterwave', false)
                    ->where('orders_accept_paypal', false)
                    ->where('orders_accept_cod', false);
            }),
            'no_whatsapp' => $query->whereDoesntHave('company.whatsappAccount', function (Builder $wa) {
                $wa->where(function (Builder $inner) {
                    $inner->where('status', 'active')
                        ->orWhereIn('onboarding_status', ['active', 'completed', 'connected']);
                });
            }),
            'trial' => $query->whereHas('company.subscriptions', function (Builder $q) {
                $q->where('status', 'trial');
            }),
            'paid' => $query->whereHas('company.subscriptions', function (Builder $q) {
                $q->where('status', 'active')->where('plan', '!=', 'free');
            }),
            default => $query,
        };
    }

    private function userInAudience(User $user, PlatformMarketingMessage $message): bool
    {
        if (! $this->isMerchant($user) || ($user->status ?? '') !== 'active' || ! $user->company_id) {
            return false;
        }

        return $this->audienceQuery($message)->where('id', $user->id)->exists();
    }

    private function popupClosed(User $user, PlatformMarketingMessage $message): bool
    {
        $period = $message->popup_once ? '' : $this->periodKey($message);

        return PlatformMarketingSend::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('channel', 'popup')
            ->where('period_key', $period)
            ->whereIn('status', [PlatformMarketingSend::STATUS_DISMISSED, PlatformMarketingSend::STATUS_SENT])
            ->exists();
    }

    private function already(PlatformMarketingMessage $message, User $user, string $channel, string $period): bool
    {
        return PlatformMarketingSend::query()
            ->where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('channel', $channel)
            ->where('period_key', $period)
            ->where('status', PlatformMarketingSend::STATUS_SENT)
            ->exists();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function record(
        PlatformMarketingMessage $message,
        User $user,
        string $channel,
        string $period,
        string $status,
        ?string $error = null,
        ?array $payload = null,
    ): void {
        PlatformMarketingSend::updateOrCreate(
            [
                'message_id' => $message->id,
                'user_id' => $user->id,
                'channel' => $channel,
                'period_key' => $period,
            ],
            [
                'company_id' => $user->company_id,
                'status' => $status,
                'error' => $error ? mb_substr($error, 0, 500) : null,
                'payload' => $payload,
                'sent_at' => in_array($status, [PlatformMarketingSend::STATUS_SENT, PlatformMarketingSend::STATUS_DISMISSED], true)
                    ? now()
                    : null,
            ]
        );
    }

    private function periodKey(PlatformMarketingMessage $message): string
    {
        if ($message->send_mode !== PlatformMarketingMessage::MODE_RECURRING) {
            return '';
        }

        return match ($message->recurring_interval) {
            'weekly' => now()->format('o-\WW'),
            'monthly' => now()->format('Y-m'),
            default => now()->toDateString(),
        };
    }

    private function emailSubject(PlatformMarketingMessage $message): string
    {
        $app = MailService::applicationName();

        return '['.$app.'] '.$message->title;
    }

    private function emailHtml(PlatformMarketingMessage $message, User $user): string
    {
        $name = trim((string) $user->name) ?: 'there';
        $html = is_string($message->body_html) && trim($message->body_html) !== ''
            ? $message->body_html
            : '<p>'.nl2br(e($message->body_text)).'</p>';
        $html = '<p>Hi '.e($name).',</p>'.$html;
        if ($message->cta_url) {
            $label = $message->cta_label ?: 'Open';
            $html .= '<p><a href="'.e($message->cta_url).'" style="display:inline-block;padding:10px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">'.e($label).'</a></p>';
        }
        if ($user->id) {
            $html .= '<p style="margin-top:24px;font-size:12px;color:#6b7280;">You opted in to product updates. <a href="'.e($this->unsubscribeUrl($user)).'">Unsubscribe</a>.</p>';
        }

        return $html;
    }

    private function whatsappText(PlatformMarketingMessage $message, User $user): string
    {
        $name = trim((string) $user->name) ?: 'there';

        return "Hi {$name}, ".$message->body_text;
    }

    private function unsubscribeUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'marketing.unsubscribe',
            now()->addDays(90),
            ['user' => $user->id]
        );
    }

    private function isMerchant(User $user): bool
    {
        return in_array($user->role, ['company_owner', 'company_admin'], true);
    }
}
