<?php



namespace App\Http\Controllers\Api;



use App\Http\Controllers\Controller;

use App\Services\Agent\Channels\ChannelIngestAuthService;

use App\Services\Agent\Channels\ChannelReplyDispatcher;

use App\Services\Agent\Channels\ChatChannel;

use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;



class WebWidgetController extends Controller

{

    public function config(Request $request, ChannelIngestAuthService $auth): JsonResponse

    {

        $validated = $request->validate([

            'companyId' => 'required|integer|exists:companies,id',

            'widgetToken' => 'required|string|max:64',

        ]);



        $company = $auth->companyFromWidgetToken((int) $validated['companyId'], $validated['widgetToken']);

        if (! $company) {

            return response()->json(['message' => 'Invalid widget token.'], 403);

        }



        $company->loadMissing('settings');



        return response()->json([

            'companyName' => $company->name,

            'greeting' => $company->settings?->ai_greeting ?? 'Hi! How can we help you today?',

            'agentEnabled' => (bool) ($company->settings?->agent_commerce_enabled ?? false),

        ]);

    }



    public function message(Request $request, ChannelIngestAuthService $auth, ChannelReplyDispatcher $dispatcher): JsonResponse

    {

        $validated = $request->validate([

            'companyId' => 'required|integer|exists:companies,id',

            'widgetToken' => 'required|string|max:64',

            'visitorId' => 'required|string|max:120',

            'message' => 'required|string|max:2000',

            'name' => 'nullable|string|max:120',

        ]);



        $company = $auth->companyFromWidgetToken((int) $validated['companyId'], $validated['widgetToken']);

        if (! $company) {

            return response()->json(['message' => 'Invalid widget token.'], 403);

        }



        $result = $dispatcher->ingestAndReply(

            $company,

            ChatChannel::WEB_WIDGET,

            $validated['visitorId'],

            $validated['message'],

            $validated['name'] ?? 'Web visitor',

            syncReply: true,

        );



        return response()->json([

            'chatId' => $result['chatId'],

            'reply' => $result['reply'],

        ], 201);

    }

}


