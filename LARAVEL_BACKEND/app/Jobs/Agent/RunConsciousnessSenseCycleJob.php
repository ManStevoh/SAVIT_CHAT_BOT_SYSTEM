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

