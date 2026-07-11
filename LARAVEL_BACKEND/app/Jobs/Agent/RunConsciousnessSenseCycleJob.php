<?php

namespace App\Jobs\Agent;

use App\Models\Company;
use App\Services\Agent\Consciousness\ConsciousnessSenseCycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunConsciousnessSenseCycleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $companyId = null) {}

    public function handle(ConsciousnessSenseCycleService $sense): void
    {
        if (! config('agent.consciousness.sense_enabled', true)) {
            return;
        }

        $query = Company::query()
            ->where('status', 'active')
            ->whereHas('settings', fn ($q) => $q->where('agent_commerce_enabled', true));

        if ($this->companyId) {
            $query->where('id', $this->companyId);
