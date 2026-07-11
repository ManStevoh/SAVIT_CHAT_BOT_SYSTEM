<?php

namespace App\Jobs\Agent;

use App\Models\Chat;
use App\Models\Company;
use App\Services\Agent\Company\ConversationReflectionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReflectOnConversationJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(
        public int $companyId,
        public int $chatId,
    ) {}

    public function uniqueId(): string
    {
        return "agent_reflect:{$this->chatId}";
    }

    public function handle(ConversationReflectionService $reflection): void
    {
        $company = Company::with('settings')->find($this->companyId);
        $chat = Chat::find($this->chatId);
        if (! $company || ! $chat) {
            return;
        }

        $reflection->reflect($company, $chat);
    }
}
