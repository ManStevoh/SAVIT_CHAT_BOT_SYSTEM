<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\MailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendNewMessageNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $companyId,
        public int $chatId,
        public string $customerPhone,
        public string $messageText,
        public ?string $customerName = null
    ) {}

    public function handle(MailService $mailService): void
    {
        $company = Company::with('settings')->find($this->companyId);
        if (! $company) {
            Log::warning('SendNewMessageNotificationJob: company not found', ['company_id' => $this->companyId]);
            return;
        }

        $email = $company->email ? trim($company->email) : '';
        if ($email === '') {
            Log::info('SendNewMessageNotificationJob: company has no email', ['company_id' => $this->companyId]);
            return;
        }

        $settings = $company->settings;
        if (! $settings || ! $settings->notifications_enabled) {
            return;
        }

        $frontendUrl = rtrim((string) env('FRONTEND_URL', config('app.url')), '/');
        $chatsUrl = $frontendUrl . '/dashboard/chats';
        $customerName = $this->customerName ? trim($this->customerName) : 'Customer';
        $messagePreview = Str::limit($this->messageText, 200);

        try {
            $mailService->sendNewMessageNotification(
                $email,
                $customerName,
                $this->customerPhone,
                $messagePreview,
                $chatsUrl
            );
        } catch (\Throwable $e) {
            Log::error('SendNewMessageNotificationJob: failed to send email', [
                'company_id' => $this->companyId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
