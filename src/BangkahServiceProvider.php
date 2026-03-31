<?php

namespace Bangkah\Starter;

use Bangkah\Starter\Console\StarterCreateCommand;
use Illuminate\Support\ServiceProvider;

class BangkahServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bindings could be added here if needed.
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                StarterCreateCommand::class,
            ]);
        }
    }
}
