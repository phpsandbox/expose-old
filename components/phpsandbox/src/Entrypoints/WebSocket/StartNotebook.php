<?php

namespace Components\PHPSandbox\Entrypoints\WebSocket;

use App\PHPSandbox\Contracts\EntrypointClientInterface;
use App\PHPSandbox\Entrypoints\Clients\Client;
use React\Promise\PromiseInterface;

class StartNotebook
{
    const ENTRYPOINT_INTERFACE = 'Components\WebSocket\Entrypoints\Notebook\NotebookEntrypointInterface';

    private static EntrypointClientInterface $client;

    public function __construct()
    {
        self::$client = app(Client::class);
    }

    public static function call(string $notebookId): PromiseInterface
    {
        new static();
        return self::$client->call(self::ENTRYPOINT_INTERFACE, 'start', ['notebookId' => $notebookId]);
    }
}

