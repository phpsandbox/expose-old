<?php

namespace Components\PHPSandbox\Server\Http\Controllers;

use App\Contracts\ConnectionManager;
use App\Contracts\StatisticsCollector;
use Components\PHPSandbox\Entrypoints\Core\GetNotebook;
use Components\PHPSandbox\Entrypoints\WebSocket\StartNotebook;
use App\Server\Configuration;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Ratchet\ConnectionInterface;

class TunnelMessageController extends \App\Server\Http\Controllers\TunnelMessageController
{
    /** @var ConnectionManager */
    protected $connectionManager;

    /** @var Configuration */
    protected $configuration;

    protected $keepConnectionOpen = true;

    protected $modifiers = [];

    /** @var StatisticsCollector */
    protected $statisticsCollector;

    public function __construct(ConnectionManager $connectionManager, StatisticsCollector $statisticsCollector, Configuration $configuration)
    {
        dump("Foooo Bar");
        parent::__construct($connectionManager, $statisticsCollector, $configuration);
        $this->connectionManager = $connectionManager;
        $this->configuration = $configuration;
        $this->statisticsCollector = $statisticsCollector;
    }

    public function handle(Request $request, ConnectionInterface $httpConnection)
    {
        $subdomain = $this->detectSubdomain($request);
        $serverHost = $this->detectServerHost($request);

        if (is_null($subdomain)) {
            $httpConnection->send(
                respond_html($this->getView($httpConnection, 'server.homepage'), 200)
            );
            $httpConnection->close();

            return;
        }

        $controlConnection = $this->connectionManager->findControlConnectionForSubdomainAndServerHost($subdomain, $serverHost);

        $send404 = function () use ($subdomain, $httpConnection) {
            $httpConnection->send(
                respond_html(
                    $this->getView($httpConnection, 'server.errors.404', ['subdomain' => $subdomain]),
                    404,
                    ['X-PHPSandbox-Message' => 'Not Found']
                )
            );
            $httpConnection->close();
        };

        $sendResponse = fn ($controlConnection) => $this->sendRequestToClient($request, $controlConnection, $httpConnection);

        $notebookAutostart = config('phpsandbox.notebooks.autostart_enabled') && ! Str::endsWith($subdomain, ['local', 'staging']);
        $notebookAutostart = $subdomain === 'laravel' || $notebookAutostart;

        if (is_null($controlConnection) && $notebookAutostart) {
            app(GetNotebook::class)
                ->call($subdomain)
                ->then(function (?array $notebook) use ($sendResponse, $send404, $subdomain) {
                    if (! $notebook) {
                        return $send404();
                    }

                    if (Arr::get($notebook, 'notebook_type.slug') === 'interactive') {
                        return $send404();
                    }

                    return $this
                        ->startNotebook($subdomain)
                        ->then(function () use ($sendResponse, $send404, $subdomain): void {
                            app(LoopInterface::class)
                                ->addTimer(2,  function () use ($sendResponse, $send404, $subdomain) {
                                    $controlConnection = $this->connectionManager->findControlConnectionForSubdomain($subdomain);

                                    if (is_null($controlConnection)) {
                                        $send404();
                                        return;
                                    }

                                    $sendResponse($controlConnection);
                                });
                        });
                })->otherwise(function (Throwable $throwable) use ($subdomain, $httpConnection) {
                    Log::error($throwable->getMessage(), $throwable->getTrace());

                    $httpConnection->send(
                        respond_html($this->getView($httpConnection, 'server.errors.500', ['subdomain' => $subdomain]),500)
                    );
                    $httpConnection->close();
                });
            return;
        } elseif (is_null($controlConnection) && ! $notebookAutostart) {
            $httpConnection->send(
                respond_html(
                    $this->getView($httpConnection, 'server.errors.404', ['subdomain' => $subdomain]),
                    404,
                    ['X-PHPSandbox-Message' => 'Not Found']
                )
            );
            $httpConnection->close();

            return;
        }

        $this->statisticsCollector->incomingRequest();

        $this->sendRequestToClient($request, $controlConnection, $httpConnection);
    }

    private function startNotebook(string $subdomain): PromiseInterface
    {
        return StartNotebook::call($subdomain);
    }
}
