<?php

namespace Mosaiqo\LaravelPayments;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Mosaiqo\LaravelPayments\Console\LemonSqueezyListenCommand;
use Mosaiqo\LaravelPayments\Http\Controllers\PaymentsCheckoutController;
use Mosaiqo\LaravelPayments\Http\Controllers\PaymentsWebhookController;

class LaravelPaymentsServiceProvider extends ServiceProvider
{

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/payments.php', 'payments');
    }

    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootRoutes();
        $this->bootMigrations();
        $this->bootCommands();
        $this->bootEvents();
    }

    protected function bootMigrations(): void
    {
        if (LaravelPayments::$runsMigrations && $this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    protected function bootRoutes(): void
    {
        if (LaravelPayments::$registersRoutes) {
            Route::group([
                'prefix' => LaravelPayments::config('path'),
                'as' => 'payments.',
            ], function () {
                Route::post(LaravelPayments::config('webhook_path'), PaymentsWebhookController::class)->name('webhooks');
                Route::get('checkouts/{product}/{variant}', PaymentsCheckoutController::class)->name('checkouts');
            });
        }
    }


    protected function bootPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/payments.php' => $this->app->configPath('payments.php'),
            ], 'payments-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'payments-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/payments'),
            ], 'payments-views');
        }
    }

    protected function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                LemonSqueezyListenCommand::class,
            ]);
        }
    }

    protected function bootEvents(): void
    {

    }
}
