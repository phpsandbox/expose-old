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

        $this->registerProviders();
        $this->registerCustomWebSocketRoute();
    }

    private function registerContainerBinds(): void
    {
        $this->app->bind(TunnelMessageController::class, Server\Http\Controllers\TunnelMessageController::class);
        $this->app->bind(\App\Commands\ServeCommand::class, ServeCommand::class);
    }

    private function registerProviders(): void
    {
    }

    private function registerCustomWebSocketRoute(): void
    {
    }

    public function boot(): void
    {
        $component = new Component('phpsandbox');

        $this->bootConfig($component);
        $this->registerCustomWebSocketActions($component);
        $this->registerCommands();
    }

    private function bootConfig(Component $component): void
    {
        $this->mergeConfigFrom($component->defaultConfigPath(), $component->name());
    }

    private function registerCustomWebSocketActions(Component $component): void
    {
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([ServeCommand::class]);
        }
    }
}
