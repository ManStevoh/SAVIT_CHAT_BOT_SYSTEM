<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function api(
    Illuminate\Contracts\Http\Kernel $kernel,
    string $method,
    string $uri,
    ?string $token = null,
    array $payload = []
): array {
    $server = [
        'HTTP_ACCEPT' => 'application/json',
    ];
    if ($token) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
    }

    $request = Illuminate\Http\Request::create($uri, $method, $payload, [], [], $server);
    $request->headers->set('Accept', 'application/json');
    if ($token) {
        $request->headers->set('Authorization', 'Bearer '.$token);
    }

    $response = $kernel->handle($request);
    $decoded = json_decode($response->getContent(), true);
    $kernel->terminate($request, $response);

    return [
        'status' => $response->getStatusCode(),
        'body' => is_array($decoded) ? $decoded : ['raw' => $response->getContent()],
    ];
}

function summarize(array $result): array
{
    $body = $result['body'];
    $checks = [];
    foreach (($body['details']['checks'] ?? []) as $check) {
        $checks[] = [
            'id' => $check['id'] ?? null,
            'status' => $check['status'] ?? null,
            'detail' => $check['detail'] ?? null,
        ];
    }

    return [
        'http' => $result['status'],
        'success' => $body['success'] ?? null,
        'message' => $body['message'] ?? null,
        'failedStep' => $body['failedStep'] ?? null,
        'failedCheck' => $body['details']['failedCheck'] ?? null,
        'graphError' => $body['details']['graphError'] ?? null,
        'hint' => $body['details']['hint'] ?? null,
        'checks' => $checks,
    ];
}

$out = [];

$login = api($kernel, 'POST', '/api/auth/login', null, [
    'email' => 'superadmin@essem.local',
    'password' => 'password',
]);
$out['login'] = [
    'http' => $login['status'],
    'success' => $login['body']['success'] ?? null,
    'role' => $login['body']['user']['role'] ?? null,
];
$token = $login['body']['token'] ?? null;
if (! is_string($token) || $token === '') {
    echo json_encode(['error' => 'admin login failed', 'login' => $out['login'], 'body' => $login['body']], JSON_PRETTY_PRINT);
    exit(1);
}

$settings = api($kernel, 'GET', '/api/admin/settings', $token);
$out['settings'] = [
    'http' => $settings['status'],
    'appId' => $settings['body']['whatsappEmbeddedAppId'] ?? null,
    'configId' => $settings['body']['whatsappEmbeddedConfigId'] ?? null,
    'hasEmbeddedSecret' => (($settings['body']['whatsappEmbeddedAppSecret'] ?? '') !== ''),
    'hasWebhookSecret' => (($settings['body']['metaAppSecret'] ?? '') !== ''),
];

$out['missing'] = summarize(api($kernel, 'POST', '/api/admin/settings/test-meta', $token, []));
$out['invalid_app_id'] = summarize(api($kernel, 'POST', '/api/admin/settings/test-meta', $token, [
    'whatsappEmbeddedAppId' => '111111111111111',
    'whatsappEmbeddedAppSecret' => 'e2e-wrong-secret',
]));
$out['invalid_secret'] = summarize(api($kernel, 'POST', '/api/admin/settings/test-meta', $token, [
    'whatsappEmbeddedAppId' => '846055524940193',
    'whatsappEmbeddedAppSecret' => 'e2e-wrong-secret',
]));

$anon = api($kernel, 'POST', '/api/admin/settings/test-meta', null, [
    'whatsappEmbeddedAppId' => '846055524940193',
]);
$out['unauthenticated'] = ['http' => $anon['status']];

$companyLogin = api($kernel, 'POST', '/api/auth/login', null, [
    'email' => 'demo1@company.local',
    'password' => 'password',
]);
$companyToken = $companyLogin['body']['token'] ?? null;
$companyCall = api($kernel, 'POST', '/api/admin/settings/test-meta', is_string($companyToken) ? $companyToken : 'invalid', [
    'whatsappEmbeddedAppId' => '846055524940193',
]);
$out['company'] = [
    'loginHttp' => $companyLogin['status'],
    'testHttp' => $companyCall['status'],
];

$failures = [];
if (($out['missing']['failedStep'] ?? null) !== 'app_id') {
    $failures[] = 'missing app id was not attributed to app_id';
}
if (($out['invalid_app_id']['failedStep'] ?? null) !== 'app_id') {
    $failures[] = 'invalid app id was not attributed to app_id';
}
if (! in_array($out['invalid_secret']['failedStep'] ?? null, ['embedded_secret', 'app_id'], true)) {
    $failures[] = 'wrong secret was not attributed to a Meta credential';
}
if (($out['unauthenticated']['http'] ?? 0) !== 401) {
    $failures[] = 'unauthenticated request was not 401';
}
if (($out['company']['testHttp'] ?? 0) !== 403) {
    $failures[] = 'company user was not forbidden';
}

$out['ok'] = $failures === [];
$out['failures'] = $failures;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit($out['ok'] ? 0 : 1);
