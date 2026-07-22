<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Safe migrations for shared hosting cron / SPanel.
 *
 * One-shot cron:
 *   php artisan migrate:via-cron --force
 *
 * Ongoing (with schedule:run):
 *   AUTO_MIGRATE=true in .env
 */
class MigrateViaCronCommand extends Command
{
    protected $signature = 'migrate:via-cron
                            {--force : Run even when AUTO_MIGRATE is disabled (for one-shot cron)}
                            {--show-cron : Print SPanel/cPanel cron lines and exit}';

    protected $description = 'Run pending migrations safely from cron (locked, production --force)';

    public function handle(): int
    {
        if ($this->option('show-cron')) {
            return $this->printCronHelp();
        }

        $autoEnabled = (bool) config('app.auto_migrate', false);
        $forced = (bool) $this->option('force');
        $interactive = $this->input->isInteractive();

        if (! $autoEnabled && ! $forced && ! $interactive) {
            Log::info('migrate:via-cron skipped (set AUTO_MIGRATE=true or pass --force)');
            $this->line('Skipped: set AUTO_MIGRATE=true or use --force.');

            return self::SUCCESS;
        }

        if (! $autoEnabled && ! $forced && $interactive) {
            if (! $this->confirm('AUTO_MIGRATE is off. Run pending migrations now?', true)) {
                return self::SUCCESS;
            }
        }

        $lock = Cache::lock('migrate-via-cron', 300);
        if (! $lock->get()) {
            $this->warn('Another migrate:via-cron is already running.');

            return self::SUCCESS;
        }

        try {
            if (! Schema::hasTable('migrations')) {
                $this->error('migrations table missing — aborting.');

                return self::FAILURE;
            }

            $this->info('Running pending migrations…');
            $exit = Artisan::call('migrate', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $output = trim(Artisan::output());
            if ($output !== '') {
                $this->line($output);
                Log::info('migrate:via-cron', ['exit' => $exit, 'output' => $output]);
            }

            return $exit === 0 ? self::SUCCESS : self::FAILURE;
        } catch (Throwable $e) {
            Log::error('migrate:via-cron failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            optional($lock)->release();
        }
    }

    private function printCronHelp(): int
    {
        $php = PHP_BINARY;
        $base = base_path();
        $artisan = $base.DIRECTORY_SEPARATOR.'artisan';
        $log = $base.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'migrate-cron.log';

        // Production path from current host log (override when printing from local).
        $prodBase = '/home/qkbghwib/chat.essemdigital.com/LARAVEL_BACKEND';
        $prodOneShot = "* * * * * cd {$prodBase} && php artisan migrate:via-cron --force >> {$prodBase}/storage/logs/migrate-cron.log 2>&1";
        $prodScheduler = "* * * * * cd {$prodBase} && php artisan schedule:run >> /dev/null 2>&1";

        $localOneShot = "* * * * * cd {$base} && {$php} {$artisan} migrate:via-cron --force >> {$log} 2>&1";

        $this->line('');
        $this->info('Run migrations via SPanel / cPanel Cron Jobs');
        $this->line('');
        $this->line('Option A — one-shot (fix audit_events / pending migrations now):');
        $this->line('  1. Cron Jobs → Add New Cron Job → Every Minute');
        $this->line('  2. Paste command, wait 1–2 minutes, check log, then DELETE the cron.');
        $this->line('');
        $this->comment($prodOneShot);
        $this->line('');
        $this->line('Local/path-detected equivalent:');
        $this->comment($localOneShot);
        $this->line('');
        $this->line('Option B — ongoing auto-migrate with Laravel scheduler:');
        $this->line('  1. .env → AUTO_MIGRATE=true');
        $this->line('  2. Keep this cron forever:');
        $this->line('');
        $this->comment($prodScheduler);
        $this->line('');
        $this->line('migrate:via-cron then runs every 5 minutes. Set AUTO_MIGRATE=false when done.');
        $this->line('');

        return self::SUCCESS;
    }
}
