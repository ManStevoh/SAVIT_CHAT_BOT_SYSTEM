<?php

namespace App\Services\Deploy;

class DeployAuditLogger
{
    /**
     * Append a structured JSON-L entry to the deploy audit log.
     *
     * @param  array<string, mixed>  $entry
     */
    public function log(array $entry): void
    {
        try {
            $logPath = (string) config('deploy.audit_log');
            $dir = dirname($logPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents(
                $logPath,
                json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable) {
            // Non-fatal — audit logging must never break a deploy
        }
    }

    /**
     * Return the most recent deploy entries (newest first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 10): array
    {
        $logPath = (string) config('deploy.audit_log');

        if (! file_exists($logPath)) {
            return [];
        }

        $lines = array_filter(explode("\n", trim((string) file_get_contents($logPath))));

        $entries = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $entries[] = $decoded;
            }
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }
}
