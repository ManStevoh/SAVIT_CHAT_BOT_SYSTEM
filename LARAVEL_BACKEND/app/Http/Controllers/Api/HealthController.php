<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->databaseStatus();
        $ok = $database === 'ok';

        return response()->json([
            'status' => $ok ? 'ok' : 'error',
            'app' => config('app.name'),
            'version' => $this->resolveVersion(),
            'checked_at' => now()->toIso8601String(),
            'checks' => [
                'app' => 'ok',
                'database' => $database,
            ],
        ], $ok ? 200 : 503);
    }

    private function databaseStatus(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function resolveVersion(): string
    {
        $configured = config('app.version');
        if (is_string($configured) && $configured !== '' && $configured !== 'dev') {
            return $configured;
        }

        $file = base_path('VERSION');
        if (is_readable($file)) {
            $fromFile = trim((string) file_get_contents($file));
            if ($fromFile !== '') {
                return $fromFile;
            }
        }

        return is_string($configured) && $configured !== '' ? $configured : 'dev';
    }
}
