<?php

namespace App\Services\Growth;

use App\Models\Company;
use App\Models\GrowthOauthState;
use App\Models\PlatformSetting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SocialOAuthService
{
    public function isConfigured(string $platform): bool
    {
        $cfg = $this->platformConfig($platform);

        return ! empty($cfg['client_id']) && ! empty($cfg['client_secret']);
    }

    public function configForPlatform(string $platform): array
    {
        return [
            'platform' => $platform,
            'configured' => $this->isConfigured($platform),
            'authorizeSupported' => in_array($platform, ['facebook', 'instagram', 'linkedin', 'tiktok', 'twitter'], true),
        ];
    }

    public function createAuthorizeUrl(Company $company, User $user, string $platform): string
    {
        if (! $this->isConfigured($platform)) {
            throw new \RuntimeException("OAuth is not configured for {$platform}. Set credentials in .env.");
        }

        if (! GrowthLimitService::canConnectPlatform($company, $platform)) {
            throw new \RuntimeException('Platform connection limit reached for your plan.');
        }

        $stateToken = Str::random(48);
        GrowthOauthState::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'platform' => $platform,
            'state_token' => $stateToken,
            'expires_at' => now()->addMinutes(15),
        ]);

        $cfg = $this->platformConfig($platform);
        $redirectUri = $this->callbackUrl();
        $scopes = is_array($cfg['scopes'] ?? null)
            ? implode(',', $cfg['scopes'])
            : (string) ($cfg['scopes'] ?? '');

        return match ($platform) {
            'facebook', 'instagram' => $this->metaAuthorizeUrl($cfg['client_id'], $redirectUri, $stateToken, $scopes),
            'linkedin' => 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
                'response_type' => 'code',
                'client_id' => $cfg['client_id'],
                'redirect_uri' => $redirectUri,
                'state' => $stateToken,
                'scope' => $scopes,
            ]),
            'tiktok' => 'https://www.tiktok.com/v2/auth/authorize/?'.http_build_query([
                'client_key' => $cfg['client_id'],
                'response_type' => 'code',
                'scope' => $scopes,
                'redirect_uri' => $redirectUri,
                'state' => $stateToken,
            ]),
            'twitter' => $this->twitterAuthorizeUrl($cfg, $stateToken, $redirectUri, $scopes),
            default => throw new \InvalidArgumentException("Unsupported platform: {$platform}"),
        };
    }

    public function handleCallback(string $platform, string $code, string $state): array
    {
        $oauthState = GrowthOauthState::where('state_token', $state)
            ->where('platform', $platform)
            ->where('expires_at', '>', now())
            ->first();

        if (! $oauthState) {
            throw new \RuntimeException('Invalid or expired OAuth state.');
        }

        $company = $oauthState->company;
        $cfg = $this->platformConfig($platform);
        $redirectUri = $this->callbackUrl();

        $tokenData = match ($platform) {
            'facebook' => $this->exchangeMetaCode($cfg, $code, $redirectUri),
            'instagram' => $this->exchangeInstagramCode($cfg, $code, $redirectUri),
            'linkedin' => $this->exchangeLinkedInCode($cfg, $code, $redirectUri),
            'tiktok' => $this->exchangeTikTokCode($cfg, $code, $redirectUri),
            'twitter' => $this->exchangeTwitterCode($cfg, $code, $redirectUri, $state),
            default => throw new \InvalidArgumentException("Unsupported platform: {$platform}"),
        };

        $account = $this->storeConnectedAccount($company, $platform, $tokenData);
        $oauthState->delete();

        return [
            'success' => true,
            'platform' => $platform,
            'accountId' => (string) $account->id,
            'accountName' => $account->account_name,
        ];
    }

    protected function storeConnectedAccount(Company $company, string $platform, array $tokenData): SocialAccount
    {
        return SocialAccount::updateOrCreate(
            [
                'company_id' => $company->id,
                'platform' => $platform,
            ],
            [
                'account_name' => $tokenData['account_name'] ?? ucfirst($platform).' Account',
                'external_account_id' => $tokenData['external_account_id'] ?? null,
                'page_id' => $tokenData['page_id'] ?? null,
                'ad_account_id' => $tokenData['ad_account_id'] ?? null,
                'access_token' => $tokenData['access_token'] ?? null,
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => $tokenData['token_expires_at'] ?? null,
                'status' => $tokenData['status'] ?? 'connected',
                'connected_at' => now(),
                'metadata' => $tokenData['metadata'] ?? null,
            ]
        );
    }

    protected function exchangeInstagramCode(array $cfg, string $code, string $redirectUri): array
    {
        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $response = Http::timeout(20)->get("{$graphUrl}/oauth/access_token", [
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (! $response->successful()) {
            Log::warning('Instagram OAuth token exchange failed', ['body' => $response->json()]);
            throw new \RuntimeException('Instagram token exchange failed.');
        }

        $shortToken = (string) $response->json('access_token');
        $userToken = $this->exchangeLongLivedToken($cfg, $shortToken) ?? $shortToken;

        $pagesResp = Http::withToken($userToken)
            ->timeout(20)
            ->get("{$graphUrl}/me/accounts", [
                'fields' => 'id,name,access_token,instagram_business_account{id,username}',
            ]);

        $pagesWithIg = collect($pagesResp->json('data', []))
            ->filter(fn ($p) => ! empty($p['instagram_business_account']['id']))
            ->values();

        if ($pagesWithIg->isEmpty()) {
            throw new \RuntimeException(
                'No Instagram Business Account found. Link an IG Professional account to a Facebook Page in Meta Business Suite.'
            );
        }

        $page = $pagesWithIg->first();
        $ig = $page['instagram_business_account'];
        $pendingPages = $pagesWithIg->count() > 1
            ? $pagesWithIg->map(fn ($p) => [
                'id' => $p['id'],
                'name' => $p['name'],
                'instagramUsername' => $p['instagram_business_account']['username'] ?? null,
            ])->all()
            : [];

        return [
            'access_token' => $page['access_token'] ?? $userToken,
            'external_account_id' => $ig['id'],
            'page_id' => $page['id'],
            'account_name' => '@'.($ig['username'] ?? 'instagram'),
            'metadata' => [
                'user_access_token' => $userToken,
                'instagram_business_account_id' => $ig['id'],
                'instagram_username' => $ig['username'] ?? null,
                'pending_pages' => $pendingPages,
            ],
            'status' => $pagesWithIg->count() > 1 ? 'pending_page_selection' : 'connected',
        ];
    }

    protected function exchangeMetaCode(array $cfg, string $code, string $redirectUri): array
    {
        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $response = Http::timeout(20)->get("{$graphUrl}/oauth/access_token", [
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (! $response->successful()) {
            Log::warning('Meta OAuth token exchange failed', ['body' => $response->json()]);
            throw new \RuntimeException('Meta token exchange failed.');
        }

        $shortToken = (string) $response->json('access_token');
        $userToken = $this->exchangeLongLivedToken($cfg, $shortToken) ?? $shortToken;

        $pagesResp = Http::withToken($userToken)
            ->timeout(20)
            ->get("{$graphUrl}/me/accounts", ['fields' => 'id,name,access_token']);

        $pages = $pagesResp->json('data', []);
        if (empty($pages)) {
            throw new \RuntimeException('No Facebook Pages found on this Meta account.');
        }

        $adsResp = Http::withToken($userToken)
            ->timeout(20)
            ->get("{$graphUrl}/me/adaccounts", ['fields' => 'id,name,account_id,currency', 'limit' => 25]);

        $adAccounts = collect($adsResp->json('data', []))->map(fn ($a) => [
            'id' => $a['id'] ?? null,
            'name' => $a['name'] ?? 'Ad Account',
            'accountId' => $a['account_id'] ?? null,
        ])->all();

        $page = $pages[0];
        $pendingPages = count($pages) > 1
            ? collect($pages)->map(fn ($p) => ['id' => $p['id'], 'name' => $p['name']])->all()
            : [];

        return [
            'access_token' => $page['access_token'] ?? $userToken,
            'external_account_id' => $page['id'] ?? null,
            'page_id' => $page['id'] ?? null,
            'account_name' => $page['name'] ?? 'Facebook Page',
            'ad_account_id' => $adAccounts[0]['id'] ?? null,
            'metadata' => [
                'user_access_token' => $userToken,
                'pending_pages' => $pendingPages,
                'ad_accounts' => $adAccounts,
            ],
            'status' => count($pages) > 1 ? 'pending_page_selection' : 'connected',
        ];
    }

    public function listPagesForCompany(Company $company, string $platform = 'facebook'): array
    {
        $account = SocialAccount::where('company_id', $company->id)
            ->where('platform', $platform)
            ->first();

        $pending = $account?->metadata['pending_pages'] ?? [];
        if (! empty($pending)) {
            return $pending;
        }

        if (! $account?->access_token) {
            return [];
        }

        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $token = $account->metadata['user_access_token'] ?? $account->access_token;
        $resp = Http::withToken($token)->timeout(20)->get("{$graphUrl}/me/accounts", ['fields' => 'id,name']);

        return collect($resp->json('data', []))->map(fn ($p) => [
            'id' => $p['id'],
            'name' => $p['name'],
        ])->all();
    }

    public function selectPage(Company $company, string $platform, string $pageId): SocialAccount
    {
        $account = SocialAccount::where('company_id', $company->id)->where('platform', $platform)->firstOrFail();
        $token = $account->metadata['user_access_token'] ?? $account->access_token;
        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');

        $fields = $platform === 'instagram'
            ? 'id,name,access_token,instagram_business_account{id,username}'
            : 'id,name,access_token';

        $resp = Http::withToken($token)->timeout(20)->get("{$graphUrl}/{$pageId}", ['fields' => $fields]);
        if (! $resp->successful()) {
            throw new \RuntimeException('Could not load selected Facebook Page.');
        }

        $page = $resp->json();
        $metadata = $account->metadata ?? [];
        unset($metadata['pending_pages']);

        $update = [
            'page_id' => $pageId,
            'account_name' => $page['name'] ?? $account->account_name,
            'access_token' => $page['access_token'] ?? $account->access_token,
            'status' => 'connected',
            'metadata' => $metadata,
        ];

        if ($platform === 'instagram') {
            $ig = $page['instagram_business_account'] ?? null;
            if (empty($ig['id'])) {
                throw new \RuntimeException('Selected Page has no linked Instagram Business Account.');
            }
            $update['external_account_id'] = $ig['id'];
            $update['account_name'] = '@'.($ig['username'] ?? 'instagram');
            $update['metadata'] = array_merge($metadata, [
                'instagram_business_account_id' => $ig['id'],
                'instagram_username' => $ig['username'] ?? null,
            ]);
        } else {
            $update['external_account_id'] = $pageId;
        }

        $account->update($update);

        return $account->fresh();
    }

    public function selectAdAccount(Company $company, string $platform, string $adAccountId): SocialAccount
    {
        $account = SocialAccount::where('company_id', $company->id)->where('platform', $platform)->firstOrFail();
        $account->update(['ad_account_id' => $adAccountId, 'status' => 'connected']);

        return $account->fresh();
    }

    protected function exchangeLongLivedToken(array $cfg, string $shortToken): ?string
    {
        $graphUrl = rtrim(config('growth.meta.graph_url'), '/');
        $response = Http::timeout(20)->get("{$graphUrl}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'fb_exchange_token' => $shortToken,
        ]);

        return $response->successful() ? (string) $response->json('access_token') : null;
    }

    protected function exchangeLinkedInCode(array $cfg, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(20)->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
        ]);

        if (! $response->successful()) {
            Log::warning('LinkedIn token exchange failed', ['body' => $response->json()]);
            throw new \RuntimeException('LinkedIn token exchange failed.');
        }

        $accessToken = (string) $response->json('access_token');
        $expiresIn = (int) $response->json('expires_in', 0);
        $refreshToken = $response->json('refresh_token');

        $profile = Http::withToken($accessToken)
            ->timeout(20)
            ->get('https://api.linkedin.com/v2/userinfo');

        $name = $profile->json('name') ?? $profile->json('given_name', 'LinkedIn Account');

        $orgsResp = Http::withToken($accessToken)
            ->timeout(20)
            ->get('https://api.linkedin.com/v2/organizationAcls', [
                'q' => 'roleAssignee',
                'role' => 'ADMINISTRATOR',
                'projection' => '(elements*(organization~(localizedName,vanityName,id)))',
            ]);

        $organizations = collect($orgsResp->json('elements', []))
            ->map(fn ($el) => [
                'id' => $el['organization~']['id'] ?? null,
                'name' => $el['organization~']['localizedName'] ?? null,
                'vanityName' => $el['organization~']['vanityName'] ?? null,
            ])
            ->filter(fn ($o) => $o['id'])
            ->values()
            ->all();

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'external_account_id' => $profile->json('sub'),
            'account_name' => $organizations[0]['name'] ?? $name,
            'token_expires_at' => $expiresIn > 0 ? now()->addSeconds($expiresIn) : null,
            'metadata' => [
                'profile_name' => $name,
                'organizations' => $organizations,
            ],
        ];
    }

    protected function exchangeTikTokCode(array $cfg, string $code, string $redirectUri): array
    {
        $response = Http::asForm()->timeout(20)->post('https://open.tiktokapis.com/v2/oauth/token/', [
            'client_key' => $cfg['client_id'],
            'client_secret' => $cfg['client_secret'],
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if (! $response->successful()) {
            Log::warning('TikTok token exchange failed', ['body' => $response->json()]);
            throw new \RuntimeException('TikTok token exchange failed.');
        }

        $data = $response->json('data', $response->json());
        $accessToken = $data['access_token'] ?? null;

        $displayName = 'TikTok Account';
        if ($accessToken) {
            $userResp = Http::withToken($accessToken)
                ->timeout(20)
                ->get('https://open.tiktokapis.com/v2/user/info/', [
                    'fields' => 'open_id,display_name,username',
                ]);
            if ($userResp->successful()) {
                $user = $userResp->json('data.user', []);
                $displayName = $user['display_name'] ?? $user['username'] ?? $displayName;
            }
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => $data['refresh_token'] ?? null,
            'external_account_id' => $data['open_id'] ?? null,
            'account_name' => $displayName,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
        ];
    }

    protected function exchangeTwitterCode(array $cfg, string $code, string $redirectUri, string $state): array
    {
        $verifier = Cache::pull("growth_oauth_twitter_pkce:{$state}");
        if (! $verifier) {
            throw new \RuntimeException('Twitter PKCE verifier expired.');
        }

        $response = Http::withBasicAuth($cfg['client_id'], $cfg['client_secret'])
            ->asForm()
            ->timeout(20)
            ->post('https://api.twitter.com/2/oauth2/token', [
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code_verifier' => $verifier,
            ]);

        if (! $response->successful()) {
            Log::warning('X token exchange failed', ['body' => $response->json()]);
            throw new \RuntimeException('X (Twitter) token exchange failed.');
        }

        $accessToken = (string) $response->json('access_token');
        $user = Http::withToken($accessToken)->timeout(20)->get('https://api.twitter.com/2/users/me');

        return [
            'access_token' => $accessToken,
            'refresh_token' => $response->json('refresh_token'),
            'external_account_id' => $user->json('data.id'),
            'account_name' => $user->json('data.username', 'X Account'),
            'token_expires_at' => isset($response->json()['expires_in'])
                ? now()->addSeconds((int) $response->json('expires_in'))
                : null,
        ];
    }

    protected function metaAuthorizeUrl(string $clientId, string $redirectUri, string $state, string $scopes): string
    {
        return 'https://www.facebook.com/v21.0/dialog/oauth?'.http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => $scopes,
            'response_type' => 'code',
        ]);
    }

    protected function twitterAuthorizeUrl(array $cfg, string $state, string $redirectUri, string $scopes): string
    {
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        Cache::put("growth_oauth_twitter_pkce:{$state}", $verifier, now()->addMinutes(15));

        return 'https://twitter.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $cfg['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => $scopes,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    protected function platformConfig(string $platform): array
    {
        $settings = Cache::remember('platform_settings_growth_oauth', 300, fn () => PlatformSetting::first());

        $metaAppId = config('growth.oauth.meta.client_id')
            ?: config('whatsapp.embedded_signup_app_id');
        $metaSecret = config('growth.oauth.meta.client_secret')
            ?: config('whatsapp.embedded_signup_app_secret');

        $configs = [
            'facebook' => [
                'client_id' => $metaAppId,
                'client_secret' => $metaSecret,
                'scopes' => 'pages_show_list,pages_read_engagement,pages_manage_posts,ads_read,business_management,public_profile',
            ],
            'instagram' => [
                'client_id' => $metaAppId,
                'client_secret' => $metaSecret,
                'scopes' => 'pages_show_list,instagram_basic,instagram_content_publish,pages_read_engagement',
            ],
            'linkedin' => [
                'client_id' => config('growth.oauth.linkedin.client_id'),
                'client_secret' => config('growth.oauth.linkedin.client_secret'),
                'scopes' => 'openid profile w_member_social r_organization_social w_organization_social',
            ],
            'tiktok' => [
                'client_id' => config('growth.oauth.tiktok.client_key'),
                'client_secret' => config('growth.oauth.tiktok.client_secret'),
                'scopes' => 'user.info.basic,video.publish',
            ],
            'twitter' => [
                'client_id' => config('growth.oauth.twitter.client_id'),
                'client_secret' => config('growth.oauth.twitter.client_secret'),
                'scopes' => 'tweet.read tweet.write users.read offline.access',
            ],
        ];

        return $configs[$platform] ?? [];
    }

    public function callbackUrl(): string
    {
        return rtrim(config('app.url'), '/').'/oauth/growth/callback';
    }

    public function frontendRedirectUrl(string $status, string $platform, ?string $message = null): string
    {
        $base = rtrim(config('app.frontend_url', config('app.url')), '/');
        $query = http_build_query(array_filter([
            'growth_oauth' => $status,
            'platform' => $platform,
            'message' => $message,
            'tab' => $status === 'pending_pages' ? 'platforms' : ($status === 'success' ? 'platforms' : null),
        ]));

        return "{$base}/dashboard/growth?{$query}";
    }
}
