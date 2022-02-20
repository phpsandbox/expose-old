<?php

namespace App\Providers;

use Components\PHPSandbox\PHPSandboxServiceProvider;
use Illuminate\Support\AggregateServiceProvider;

class ComponentServiceProvider extends AggregateServiceProvider
{
    protected $providers = [
        PHPSandboxServiceProvider::class,
    ];
}
