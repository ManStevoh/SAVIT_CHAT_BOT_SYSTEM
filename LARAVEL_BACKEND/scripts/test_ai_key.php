<?php
/**
 * Test AI API key quota — run with: php artisan tinker < scripts/test_ai_key.php
 */

use App\Models\AiProvider;
use App\Models\AiModel;
use App\Models\Company;
use App\Models\AiRequestLog;

echo "\n=== AI Provider Status ===\n\n";

$providers = AiProvider::all();
if ($providers->isEmpty()) {
    echo "❌ No AI providers configured in the database.\n";
    exit(1);
}

foreach ($providers as $p) {
    $keyStatus = $p->hasConfiguredApiKey() ? '✅ Key set' : '❌ No key';
    $enabled = $p->is_enabled ? '🟢 Enabled' : '🔴 Disabled';
    $baseUrl = $p->api_base_url ?: '(default)';
    echo "  [{$p->id}] {$p->name} ({$p->slug})\n";
    echo "      Status: {$enabled}  |  {$keyStatus}\n";
    echo "      Base URL: {$baseUrl}\n";

    $models = AiModel::where('ai_provider_id', $p->id)->get();
    foreach ($models as $m) {
        $modelEnabled = $m->is_enabled ? '🟢' : '🔴';
        echo "      {$modelEnabled} Model: {$m->model_key} (capabilities: " . implode(', ', $m->capabilities ?? []) . ")\n";
    }
    echo "\n";
}

echo "=== Recent AI Request Logs (last 10) ===\n\n";
$logs = AiRequestLog::orderByDesc('created_at')->limit(10)->get();
if ($logs->isEmpty()) {
    echo "  No AI request logs found.\n\n";
} else {
    foreach ($logs as $log) {
        $status = $log->success ? '✅' : '❌';
        $error = $log->error_message ? " | Error: {$log->error_message}" : '';
        $http = $log->http_status ? " | HTTP {$log->http_status}" : '';
        echo "  {$status} [{$log->created_at}] use_case={$log->use_case} model={$log->model} latency={$log->latency_ms}ms{$http}{$error}\n";
    }
    echo "\n";
}

echo "=== Testing API Key with Minimal Request ===\n\n";

$enabledProvider = AiProvider::where('is_enabled', true)->first();
if (!$enabledProvider) {
    echo "❌ No enabled provider found. Cannot test API.\n";
    exit(1);
}

if (!$enabledProvider->hasConfiguredApiKey()) {
    echo "❌ Provider '{$enabledProvider->name}' has no API key configured.\n";
    exit(1);
}

echo "Testing provider: {$enabledProvider->name} ({$enabledProvider->slug})\n";

$model = AiModel::where('ai_provider_id', $enabledProvider->id)
    ->where('is_enabled', true)
    ->first();

if (!$model) {
    echo "❌ No enabled model found for provider '{$enabledProvider->name}'.\n";
    exit(1);
}

echo "Using model: {$model->model_key}\n\n";

// Make a minimal test call
try {
    $driver = app(\App\Services\AI\AiDriverFactory::class)->driverFor($enabledProvider);
    $resolved = new \App\Services\AI\ResolvedAiModel(
        provider: $enabledProvider,
        model: $model,
        credentialSource: 'platform',
        selectionSource: 'test',
    );

    $result = $driver->chatCompletion(
        $resolved,
        [
            ['role' => 'system', 'content' => 'Reply with exactly: OK'],
            ['role' => 'user', 'content' => 'Hi'],
        ],
        maxTokens: 10,
        temperature: 0,
        jsonMode: false,
        timeoutSeconds: 15,
    );

    if ($result->success) {
        echo "✅ API call SUCCESS!\n";
        echo "   Response: " . mb_substr($result->content ?? '(empty)', 0, 100) . "\n";
        echo "   Model: {$result->model}\n";
        echo "   Tokens: prompt={$result->promptTokens}, completion={$result->completionTokens}\n";
        echo "   Latency: {$result->latencyMs}ms\n";
        echo "   HTTP Status: {$result->httpStatus}\n";
        echo "\n🟢 Your API key is working and quota is NOT exhausted.\n";
    } else {
        echo "❌ API call FAILED!\n";
        echo "   HTTP Status: {$result->httpStatus}\n";
        echo "   Error: {$result->error}\n";
        if ($result->httpStatus === 429) {
            echo "\n🔴 RATE LIMITED — You've hit the API rate limit. Wait a minute and try again.\n";
        } elseif ($result->httpStatus === 402 || $result->httpStatus === 403) {
            echo "\n🔴 QUOTA EXHAUSTED or BILLING ISSUE — Check your API provider billing dashboard.\n";
        } elseif ($result->httpStatus === 401) {
            echo "\n🔴 INVALID API KEY — The key is rejected by the provider.\n";
        } else {
            echo "\n🟡 Unknown error. Check the error message above.\n";
        }
    }
} catch (\Throwable $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";

    if (str_contains($e->getMessage(), 'quota') || str_contains($e->getMessage(), 'billing')) {
        echo "\n🔴 QUOTA/BILLING issue detected.\n";
    } elseif (str_contains($e->getMessage(), 'Could not resolve host') || str_contains($e->getMessage(), 'timed out')) {
        echo "\n🟡 NETWORK issue — cannot reach the API endpoint.\n";
    }
}

echo "\n";
