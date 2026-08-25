<?php
// Run: php artisan tinker < scripts/check_ai_logs.php

echo "\n=== AI Provider Status ===\n\n";

$providers = \App\Models\AiProvider::all();
if ($providers->isEmpty()) {
    echo "No AI providers found.\n";
} else {
    foreach ($providers as $p) {
        $keyStatus = $p->hasConfiguredApiKey() ? 'Key set' : 'NO KEY';
        $enabled = $p->is_enabled ? 'Enabled' : 'Disabled';
        echo "  [{$p->id}] {$p->name} ({$p->slug}) | {$enabled} | {$keyStatus}\n";
        $models = \App\Models\AiModel::where('ai_provider_id', $p->id)->get();
        foreach ($models as $m) {
            $me = $m->is_enabled ? 'ON' : 'OFF';
            $caps = implode(', ', $m->capabilities ?? []);
            echo "      {$me} {$m->model_key} [{$caps}]\n";
        }
    }
}

echo "\n=== Last 15 AI Request Logs ===\n\n";

$logs = \App\Models\AiRequestLog::orderByDesc('created_at')->limit(15)->get();
if ($logs->isEmpty()) {
    echo "  No logs found.\n";
} else {
    foreach ($logs as $l) {
        $s = $l->success ? 'OK' : 'FAIL';
        $err = $l->error_message ? " | ERR: {$l->error_message}" : '';
        echo "  [{$s}] {$l->created_at} | {$l->use_case} | {$l->model} | HTTP:{$l->http_status} | {$l->latency_ms}ms{$err}\n";
    }
}

echo "\n=== 24h Summary ===\n\n";

$since = now()->subHours(24);
$total = \App\Models\AiRequestLog::where('created_at', '>=', $since)->count();
$failed = \App\Models\AiRequestLog::where('created_at', '>=', $since)->where('success', false)->count();
$pct = $total > 0 ? round($failed / $total * 100) : 0;
echo "  Total: {$total} | Failed: {$failed} ({$pct}%)\n";

$errors = \App\Models\AiRequestLog::where('created_at', '>=', $since)
    ->where('success', false)
    ->whereNotNull('error_message')
    ->selectRaw('error_message, count(*) as cnt')
    ->groupBy('error_message')
    ->orderByDesc('cnt')
    ->limit(5)
    ->get();

if ($errors->isNotEmpty()) {
    echo "\n  Top errors:\n";
    foreach ($errors as $e) {
        echo "    [{$e->cnt}x] {$e->error_message}\n";
    }
}

echo "\n";
