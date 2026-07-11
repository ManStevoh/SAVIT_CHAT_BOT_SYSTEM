<?php

namespace App\Http\Middleware;

use App\Services\Platform\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        protected ApiKeyService $apiKeys,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization', '');
        $plain = str_starts_with($header, 'Bearer ') ? substr($header, 7) : $request->header('X-Api-Key');

        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'API key required.'], 401);
        }

        $key = $this->apiKeys->authenticate($plain);
        if (! $key) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $request->attributes->set('api_key', $key);
        $request->attributes->set('api_key_company_id', $key->company_id);

        return $next($request);
    }
}
