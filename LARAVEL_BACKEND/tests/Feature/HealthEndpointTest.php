<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    public function test_api_health_returns_ok_payload(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('checks.app', 'ok')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure([
                'status',
                'app',
                'version',
                'checked_at',
                'checks' => ['app', 'database'],
            ]);
    }

    public function test_legacy_up_endpoint_is_healthy(): void
    {
        $this->get('/up')->assertOk();
    }
}
