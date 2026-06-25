<?php

namespace App\Services;

use App\Models\Company;
use App\Services\Conversation\AiReplyModeResolver;
use App\Services\Conversation\ConversationGreetingService;
use App\Services\Conversation\ConversationRoutingLogger;
use App\Services\Conversation\WhatsAppReplyRouter;
use App\Services\Conversation\WorkingHoursService;

/**
 * Orchestrates WhatsApp bot reply generation (greetings, routing, AI).
 */
class AIReplyService
{
    protected ?string $lastReplyRoute = null;

    /** Numbered quick menu (greetings, handoff, etc.). */
    public const QUICK_MENU_SUFFIX = ConversationGreetingService::QUICK_MENU_SUFFIX;

    public function __construct(
        protected AiReplyModeResolver $modeResolver,
        protected ConversationGreetingService $greetingService,
        protected WorkingHoursService $workingHours,
        protected WhatsAppReplyRouter $replyRouter,
        protected ConversationRoutingLogger $routingLogger,
    ) {}

    public function getLastReplyRoute(): ?string
    {
        return $this->lastReplyRoute;
    }

    public function usesAiFirstRouting(Company $company): bool
    {
        return $this->modeResolver->isAiFirst($company);
    }

    public function shouldSkipScriptedOpening(Company $company, string $message): bool
    {
        return $this->modeResolver->shouldSkipScriptedOpening($company, $message, $this->greetingService);
    }

    public function isPureGreeting(string $message): bool
    {
        return $this->greetingService->isPureGreeting($message);
    }

    public function getReplyForMessage(
        Company $company,
        string $customerMessage,
        ?string $customerName = null,
        ?int $chatId = null,
        ?string $orderFlowContext = null,
    ): string {
        $company->loadMissing('settings');

        $message = trim($customerMessage);
        if ($message === '') {
            return $this->routeAndReturn($company, $chatId, 'empty_message', $this->replyRouter->fallbackMessage($company));
        }

        if ($this->workingHours->isOutsideWorkingHours($company)) {
            return $this->routeAndReturn($company, $chatId, 'away_hours', $this->workingHours->awayMessage($company));
        }

        $substantive = $this->greetingService->stripLeadingGreeting($message);
        if ($substantive !== '' && $substantive !== $message) {
            return $this->resolveRouted($company, $substantive, $customerName, $chatId, $orderFlowContext);
        }

        if ($this->greetingService->isPureGreeting($message)) {
            return $this->routeAndReturn(
                $company,
                $chatId,
                'greeting_menu',
                $this->greetingService->buildOpening($company, $customerName),
            );
        }

        return $this->resolveRouted($company, $message, $customerName, $chatId, $orderFlowContext);
    }

    public function getReplyAfterOpeningGreeting(
        Company $company,
        string $customerMessage,
        ?string $customerName = null,
        ?int $chatId = null,
        ?string $orderFlowContext = null,
    ): ?string {
        $company->loadMissing('settings');

        $message = trim($customerMessage);
        if ($message === '' || $this->workingHours->isOutsideWorkingHours($company)) {
            return null;
        }

        $substantive = $this->greetingService->stripLeadingGreeting($message);
        if ($substantive === '' || $this->greetingService->isPureGreeting($message)) {
            return null;
        }

        $text = $substantive !== $message ? $substantive : $message;

        return $this->resolveRouted($company, $text, $customerName, $chatId, $orderFlowContext);
    }

    public function getGreetingOpening(Company $company, ?string $customerName = null): string
    {
        $company->loadMissing('settings');

        if ($this->workingHours->isOutsideWorkingHours($company)) {
            $this->setReplyRoute('away_hours');

            return $this->workingHours->awayMessage($company);
        }

        $this->setReplyRoute('greeting_menu');

        return $this->greetingService->buildOpening($company, $customerName);
    }

    protected function resolveRouted(
        Company $company,
        string $message,
        ?string $customerName,
        ?int $chatId,
        ?string $orderFlowContext,
    ): string {
        $result = $this->replyRouter->resolve(
            $company,
            $message,
            mb_strtolower($message),
            $customerName,
            $chatId,
            $orderFlowContext,
        );

        $this->routingLogger->log($company->id, $chatId, $result['route'], $result['meta'] ?? []);
        $this->setReplyRoute($result['route']);

        return $result['text'];
    }

    protected function routeAndReturn(Company $company, ?int $chatId, string $route, string $text): string
    {
        $this->routingLogger->log($company->id, $chatId, $route);
        $this->setReplyRoute($route);

        return $text;
    }

    protected function setReplyRoute(string $route): void
    {
        $this->lastReplyRoute = $route;
    }
}
