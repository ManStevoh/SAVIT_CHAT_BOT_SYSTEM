<?php

namespace App\Console\Commands;

use App\Services\Deploy\DeployExecutionService;
use Illuminate\Console\Command;

/**
 * Internal command — invoked by DeployExecutionService::startBackground().
 * Not intended for manual use.
 */
class DeployRunCommand extends Command
{
    protected $signature   = 'deploy:run {token} {branch}';
    protected $description = 'Execute a background deployment (internal — do not call manually)';
    protected $hidden      = true;

    public function __construct(
        private readonly DeployExecutionService $execution,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $token  = (string) $this->argument('token');
        $branch = (string) $this->argument('branch');

        // Validate token format — must be exactly 32 alphanumeric characters
        if (! preg_match('/^[a-zA-Z0-9]{32}$/', $token)) {
            $this->error('Invalid deploy token format.');
            return self::FAILURE;
        }

        // Sanitise branch (same rules as WebDeployController)
        $cleanBranch = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $branch) ?: 'main';

        $this->execution->execute($token, $cleanBranch);

        return self::SUCCESS;
    }
}
