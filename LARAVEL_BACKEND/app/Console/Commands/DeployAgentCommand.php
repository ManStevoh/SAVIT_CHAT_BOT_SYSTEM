<?php

namespace App\Console\Commands;

use App\Services\Deploy\DeployAuthService;
use App\Services\Deploy\DeployExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DeployAgentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:agent
                            {branch=main : Target Git branch to deploy}
                            {--force : Force deployment execution without interactive confirmation}
                            {--remote= : Optional remote base URL (e.g. https://essemchat.essemglobalsolutions.com)}
                            {--local : Force local execution even if DEPLOY_REMOTE_URL is configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Trigger an automated deployment pipeline via Agent Navigation Mode';

    public function __construct(
        private readonly DeployAuthService     $authService,
        private readonly DeployExecutionService $execution,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $branch     = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', (string) $this->argument('branch')) ?: 'main';
        $remoteUrl  = (string) ($this->option('remote') ?: config('deploy.remote_url'));
        $forceLocal = (bool) $this->option('local');

        $this->info("🤖 [Agent Navigation Mode] Preparing deployment for branch: [{$branch}]");

        if (! empty($remoteUrl) && ! $forceLocal) {
            return $this->handleRemoteDeploy(rtrim($remoteUrl, '/'), $branch);
        }

        return $this->handleLocalDeploy($branch);
    }

    /**
     * Execute deployment locally via DeployExecutionService.
     */
    private function handleLocalDeploy(string $branch): int
    {
        if ($this->execution->isLocked()) {
            $this->error('⚠️  [LOCK_CONFLICT] A deployment is already running on this server.');
            return self::FAILURE;
        }

        $this->line("🚀 Initiating local pipeline execution for branch [{$branch}]...");
        $startTime = microtime(true);

        $result = $this->execution->runStreamed($branch, function (string $line) {
            $this->outputLine($line);
        });

        $duration = $result['duration'] ?? round(microtime(true) - $startTime, 2);

        if ($result['success'] ?? false) {
            $this->newLine();
            $this->info("✨ [SUCCESS] Agent deployment completed successfully in {$duration}s!");
            return self::SUCCESS;
        }

        $this->newLine();
        $this->error("❌ [FAILURE] Deployment failed after {$duration}s.");
        return self::FAILURE;
    }

    /**
     * Trigger deployment remotely via HTTP API stream.
     */
    private function handleRemoteDeploy(string $remoteUrl, string $branch): int
    {
        $agentKey = (string) (config('deploy.agent_key') ?: config('deploy.secret'));

        if (empty($agentKey)) {
            $this->error('❌ [CONFIG_ERROR] Neither DEPLOY_AGENT_KEY nor DEPLOY_SECRET is configured in .env.');
            return self::FAILURE;
        }

        $endpoint = "{$remoteUrl}/deploy/agent";
        $this->line("🌐 Connecting to remote gateway: {$endpoint}...");

        try {
            $response = Http::withHeaders([
                'X-Deploy-Agent-Key' => $agentKey,
                'Accept'             => 'text/event-stream, application/json',
            ])->timeout(300)->post($endpoint, [
                'branch' => $branch,
            ]);

            if ($response->status() === 401 || $response->status() === 403) {
                $this->error('❌ [AUTH_ERROR] Remote server rejected deploy key. Verify DEPLOY_AGENT_KEY in .env.');
                return self::FAILURE;
            }

            if ($response->status() === 409) {
                $this->error('⚠️  [LOCK_CONFLICT] A deployment is currently in progress on the remote server.');
                return self::FAILURE;
            }

            $body = $response->body();

            // Parse lines from streamed response
            foreach (explode("\n", $body) as $rawLine) {
                $trimmed = trim($rawLine);
                if (str_starts_with($trimmed, 'data:')) {
                    $jsonStr = trim(substr($trimmed, 5));
                    $data = json_decode($jsonStr, true);
                    if (is_array($data)) {
                        if (($data['type'] ?? '') === 'log' && ! empty($data['line'])) {
                            $this->outputLine($data['line']);
                        } elseif (($data['type'] ?? '') === 'done') {
                            $this->newLine();
                            if ($data['success'] ?? false) {
                                $this->info("✨ [SUCCESS] Remote deployment completed in " . ($data['duration'] ?? 0) . "s!");
                                return self::SUCCESS;
                            } else {
                                $this->error("❌ [FAILURE] Remote deployment reported failure.");
                                return self::FAILURE;
                            }
                        }
                    }
                } elseif ($trimmed !== '') {
                    $this->line($trimmed);
                }
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ [NETWORK_ERROR] Failed to communicate with remote deploy gateway: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Format and output styled log lines to CLI.
     */
    private function outputLine(string $line): void
    {
        if (str_contains($line, '✅') || str_contains($line, '[SUCCESS]') || str_contains($line, 'DONE')) {
            $this->line("<info>{$line}</info>");
        } elseif (str_contains($line, '❌') || str_contains($line, '[EXCEPTION]') || str_contains($line, '[ERROR]')) {
            $this->line("<error>{$line}</error>");
        } elseif (str_contains($line, '⚠️') || str_contains($line, '[WARN]')) {
            $this->line("<comment>{$line}</comment>");
        } else {
            $this->line("  {$line}");
        }
    }
}
