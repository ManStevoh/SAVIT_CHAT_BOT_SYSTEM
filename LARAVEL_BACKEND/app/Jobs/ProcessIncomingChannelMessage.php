<?php

namespace App\Jobs;

use App\Models\Chat;
use App\Models\Company;
use App\Models\Message;
use App\Services\Agent\Channels\ChatChannel;
use App\Services\Agent\CommerceAgentReplyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Agent reply for web widget, email, and Instagram DM channels (no WhatsApp send).
