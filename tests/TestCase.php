<?php

namespace Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Mosaiqo\LaravelPayments\LaravelPaymentsServiceProvider;
use Orchestra\Testbench\Concerns\WithLaravelMigrations;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Tests\Fixtures\User;


class TestCase extends OrchestraTestCase
{
    use WithLaravelMigrations;
    use InteractsWithExceptionHandling;

    protected function getPackageProviders($app)
    {
        return [
            LaravelPaymentsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Relation::enforceMorphMap([
            'user' => User::class
        ]);
    }
}
