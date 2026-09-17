<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MailService;
use App\Services\MerchantLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MarketingUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user, MerchantLifecycleService $lifecycle): Response
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'This unsubscribe link is invalid or has expired.');
        }

        $lifecycle->unsubscribe($user);
        $app = MailService::applicationName();

        return response()->view('marketing-unsubscribed', [
            'appName' => $app,
            'email' => $user->email,
        ]);
    }
}
