<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('inertia.testing.ensure_pages_exist', false);

        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->app && $this->app->resolved('db')) {
                $db = $this->app->make('db');
                foreach ($db->getConnections() as $connection) {
                    try {
                        while ($connection->transactionLevel() > 0) {
                            $connection->rollBack();
                        }
                        $pdo = $connection->getPdo();
                        if ($pdo && method_exists($pdo, 'inTransaction') && $pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                    } catch (\Throwable) {
                        // ignore
                    }
                }
            }
        } catch (\Throwable) {
            // ignore
        }

        parent::tearDown();
    }
}
