<?php

namespace App\Server\Http\Controllers;

use App\Contracts\ConnectionManager;
use App\Contracts\StatisticsCollector;
use App\Http\Controllers\Controller;
use App\PHPSandbox\Entrypoints\Core\GetNotebook;
use App\PHPSandbox\Entrypoints\WebSocket\StartNotebook;
use App\Server\Configuration;
use App\Server\Connections\ControlConnection;
use App\Server\Connections\HttpConnection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use Throwable;
use function GuzzleHttp\Psr7\str;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Nyholm\Psr7\Factory\Psr17Factory;
use Ratchet\ConnectionInterface;
use Ratchet\RFC6455\Messaging\Frame;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

class TunnelMessageController extends Controller
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

    protected function detectSubdomain(Request $request): ?string
    {
        $serverHost = $this->detectServerHost($request);

        $subdomain = Str::before($request->header('Host'), '.'.$serverHost);

        return $subdomain === $request->header('Host') ? null : $subdomain;
    }

    protected function detectServerHost(Request $request): ?string
    {
        return Str::before(Str::after($request->header('Host'), '.'), ':');
    }

    protected function sendRequestToClient(Request $request, ControlConnection $controlConnection, ConnectionInterface $httpConnection)
    {
        $request = $this->prepareRequest($request, $controlConnection);

        $requestId = $request->header('X-Expose-Request-ID');

        $httpConnection = $this->connectionManager->storeHttpConnection($httpConnection, $requestId);

        transform($this->passRequestThroughModifiers($request, $httpConnection), function (Request $request) use ($controlConnection, $requestId) {
            $controlConnection->once('proxy_ready_'.$requestId, function (ConnectionInterface $proxy) use ($request) {
                // Convert the Laravel request into a PSR7 request
                $psr17Factory = new Psr17Factory();
                $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
                $request = $psrHttpFactory->createRequest($request);

                $binaryMsg = new Frame(str($request), true, Frame::OP_BINARY);
                $proxy->send($binaryMsg);
            });

            $controlConnection->registerProxy($requestId);
        });
    }

    protected function passRequestThroughModifiers(Request $request, HttpConnection $httpConnection): ?Request
    {
        foreach ($this->modifiers as $modifier) {
            $request = app($modifier)->handle($request, $httpConnection);

            if (is_null($request)) {
                break;
            }
        }

        return $request;
    }

    protected function prepareRequest(Request $request, ControlConnection $controlConnection): Request
    {
        $request::setTrustedProxies([$controlConnection->socket->remoteAddress, '127.0.0.1'], Request::HEADER_X_FORWARDED_ALL);

        $host = $controlConnection->serverHost;

        if (! $request->isSecure()) {
            $host .= ":{$this->configuration->port()}";
        }

        $request->headers->set('Host', $controlConnection->host);
        $request->headers->set('X-Forwarded-Proto', $request->isSecure() ? 'https' : 'http');
        $request->headers->set('X-Expose-Request-ID', uniqid());
        $request->headers->set('Upgrade-Insecure-Requests', 1);
        $request->headers->set('X-Exposed-By', config('app.name').' '.config('app.version'));
        $request->headers->set('X-Original-Host', "{$controlConnection->subdomain}.{$host}");
        $request->headers->set('X-Forwarded-Host', "{$controlConnection->subdomain}.{$host}");

        return $request;
    }
}
