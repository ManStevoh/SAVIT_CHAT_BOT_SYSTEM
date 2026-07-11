<?php



namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;

use App\Services\Agent\Channels\ChannelIngestAuthService;

use App\Services\Agent\Channels\ChannelReplyDispatcher;

use App\Services\Agent\Channels\ChatChannel;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class WebWidgetController extends Controller

