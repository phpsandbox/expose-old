<?php

namespace Components\PHPSandbox;

use App\Component;
use App\Server\Http\Controllers\TunnelMessageController;
use Components\PHPSandbox\Commands\ServeCommand;
use Illuminate\Support\ServiceProvider;

class PHPSandboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerContainerBinds();
    }

    private function registerContainerBinds(): void
    {
        $this->app->bind(TunnelMessageController::class, Server\Http\Controllers\TunnelMessageController::class);
        $this->app->bind(\App\Commands\ServeCommand::class, ServeCommand::class);
    }

    public function boot(): void
    {
        $component = new Component('phpsandbox');

        $this->bootConfig($component);
        $this->registerCommands();
    }

    private function bootConfig(Component $component): void
    {
        $this->mergeConfigFrom($component->defaultConfigPath(), $component->name());
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ServeCommand::class]);
        }
    }
}
