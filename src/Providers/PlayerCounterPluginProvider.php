<?php

namespace Finxnz\PlayerCounter\Providers;

use Illuminate\Support\ServiceProvider;

class PlayerCounterPluginProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
