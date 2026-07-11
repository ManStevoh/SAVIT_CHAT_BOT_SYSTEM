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



