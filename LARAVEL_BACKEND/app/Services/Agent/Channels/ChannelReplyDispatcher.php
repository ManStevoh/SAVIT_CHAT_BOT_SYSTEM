<?php

namespace App\Services\Agent\Channels;

use App\Jobs\ProcessIncomingChannelMessage;
use App\Models\Company;
use App\Models\Message;

/**
 * Ingest + optional synchronous agent reply (web widget, webhooks).
 */
final class ChannelReplyDispatcher
