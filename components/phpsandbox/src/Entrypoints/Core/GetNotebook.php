<?php

namespace Components\PHPSandbox\Entrypoints\Core;

use App\PHPSandbox\Entrypoints\Clients\CoreClient;
use React\Promise\PromiseInterface;

class GetNotebook
{
    const ENTRYPOINT_INTERFACE = 'App\Entrypoint\Notebook\NotebookEntrypointInterface';

    public function __construct(private CoreClient $client)
    {
    }

    public function call(string $notebookId, array $fetchOptions = ['withTunnelApi', 'withDisk', 'withParent'], array $headers = []): PromiseInterface
    {
        return $this->client->call(
            self::ENTRYPOINT_INTERFACE,
            'getNotebook',
            ['notebookId' => $notebookId, 'fetchOptions' => $fetchOptions],
            $headers
        );
    }
}
