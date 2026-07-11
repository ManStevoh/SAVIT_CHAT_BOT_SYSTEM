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
