<?php

namespace App\PHPSandbox\Contracts;

use React\Promise\PromiseInterface;

interface EntrypointClientInterface
{
    public function call(string $entrypointInterface, string $method, array $args = [], array $headers = []): PromiseInterface;
}
