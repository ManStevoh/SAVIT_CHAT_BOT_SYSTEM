<?php

namespace App\Jobs\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Services\Agent\CustomerMemoryExtractionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExtractCustomerMemoriesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $companyId,
        public int $chatId,
        public string $customerPhone,
    ) {}

    public function uniqueId(): string
    {
        return "agent_memory_extract:{$this->chatId}";
    }

    public function handle(CustomerMemoryExtractionService $extractor): void
    {
        $company = Company::with('settings')->find($this->companyId);
        $chat = Chat::find($this->chatId);
        if (! $company || ! $chat) {
            return;
        }

        $extractor->extractFromChat($company, $chat, $this->customerPhone);
    }
}
