<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MigrateViaCronCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_cron_prints_instructions(): void
    {
        $this->artisan('migrate:via-cron', ['--show-cron' => true])
            ->expectsOutputToContain('SPanel')
            ->expectsOutputToContain('migrate:via-cron --force')
            ->assertSuccessful();
    }

    public function test_force_runs_migrate_successfully(): void
    {
        $exit = Artisan::call('migrate:via-cron', ['--force' => true]);
        $this->assertSame(0, $exit);
    }
}
