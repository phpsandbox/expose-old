<?php

namespace App\PHPSandbox\Entrypoints\Clients;

use App\PHPSandbox\Contracts\EntrypointClientInterface;
use App\PHPSandbox\Entrypoints\Exceptions\EntrypointException;
use Closure;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use React\Http\Message\ResponseException;
use React\Promise\PromiseInterface;

class CoreClient implements EntrypointClientInterface
{
    const ENTRYPOINT_URI = '/entrypoint';

    private Browser $httpClient;

    public function __construct()
    {
        $this->httpClient = (new Browser(app(LoopInterface::class)))->withBase(config('phpsandbox.core_entrypoint.base_url'));
    }

    public function call(string $entrypointInterface, string $method, array $args = [], array $headers = []): PromiseInterface
    {
        $payload = [
            'entrypoint' => $entrypointInterface,
            'method' => $method,
            'args' => $args,
        ];

        $responsePromise = $this->httpClient->post(
            self::ENTRYPOINT_URI,
            array_merge($this->defaultHeaders(), $headers, $this->basicAuthHeaders()),
            json_encode($payload)
        );

        dump($payload, config('phpsandbox.core_entrypoint.base_url'));

        return $responsePromise
            ->then(fn (ResponseInterface $response) => $this->handleResponse($response, $payload))
            ->otherwise(Closure::fromCallable([$this, 'handleResponseException']));
    }

    private function defaultHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    private function basicAuthHeaders(): array
    {
        $username = config('phpsandbox.core_entrypoint.basic_auth_username');
        $password = config('phpsandbox.core_entrypoint.basic_auth_password');

        $token = base64_encode("$username:$password");

        return [
            'Authorization' => "Basic $token",
        ];
    }

    private function handleResponseException(ResponseException $exception): void
    {
        throw $exception;
    }

    private function handleResponse(ResponseInterface $response, array $payload): mixed
    {
        $responseArr = json_decode((string) $response->getBody(), true);

        if (Arr::has($responseArr, 'error')) {
            throw match (Arr::get($responseArr, 'error.code', 500)) {
                404 => EntrypointException::notFound($payload['entryoint']),
                default => new EntrypointException(
                    sprintf(
                        'Calling `%s@%s` with %s errored: %s',
                        $payload['entrypoint'],
                        $payload['method'],
                        json_encode($payload['args'] ?? []),
                        Arr::get($responseArr, 'error.message')
                    ),
                    Arr::get($responseArr, 'error.code')
                )
            };
        }

        return Arr::get($responseArr, 'data');
    }
}
