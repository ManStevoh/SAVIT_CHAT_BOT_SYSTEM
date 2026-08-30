<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\File;
use Throwable;

class WhatsAppDebugLogger
{
    public static function log(string $step, array $context = [], string $level = 'INFO'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $jsonContext = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $line = "[{$timestamp}] [{$level}] [{$step}] {$jsonContext}\n";

        // Append to storage/logs/whatsapp_debug.log
        try {
            $logPath = storage_path('logs/whatsapp_debug.log');
            $dir = dirname($logPath);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            File::append($logPath, $line);
        } catch (Throwable) {
            // Ignore file write issues
        }
    }

    public static function registerShutdownHandler(array $context = []): void
    {
        register_shutdown_function(function () use ($context) {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                self::log('FATAL_PHP_SHUTDOWN_ERROR', array_merge($context, [
                    'error_type' => $error['type'],
                    'message' => $error['message'],
                    'file' => basename($error['file']),
                    'line' => $error['line'],
                ]), 'CRITICAL');
            }
        });
    }

    public static function info(string $step, array $context = []): void
    {
        self::log($step, $context, 'INFO');
    }

    public static function warning(string $step, array $context = []): void
    {
        self::log($step, $context, 'WARNING');
    }

    public static function error(string $step, array $context = [], ?Throwable $e = null): void
    {
        if ($e !== null) {
            $context['error_message'] = $e->getMessage();
            $context['error_file'] = basename($e->getFile());
            $context['error_line'] = $e->getLine();
            $context['error_trace'] = mb_substr($e->getTraceAsString(), 0, 500);
        }
        self::log($step, $context, 'ERROR');
    }

    public static function clear(): void
    {
        try {
            File::put(storage_path('logs/whatsapp_debug.log'), '');
        } catch (Throwable) {
        }
    }
}

