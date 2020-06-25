<?php

namespace App\Server\Http\Controllers;

use App\Contracts\ConnectionManager;
use App\Http\Controllers\Controller;
use App\Server\Configuration;
use App\Server\Connections\ControlConnection;
use App\Server\Connections\HttpConnection;
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

    public function __construct(ConnectionManager $connectionManager, Configuration $configuration)
    {
        $this->connectionManager = $connectionManager;
        $this->configuration = $configuration;
    }

    public function handle(Request $request, ConnectionInterface $httpConnection)
    {
        var_dump("1");
        $subdomain = $this->detectSubdomain($request);

        if (is_null($subdomain)) {
            $httpConnection->send(
                respond_html($this->getView($httpConnection, 'server.homepage'), 200)
            );
            $httpConnection->close();

            return;
        }

        var_dump("2");

        $controlConnection = $this->connectionManager->findControlConnectionForSubdomain($subdomain);

        if (is_null($controlConnection)) {
            $httpConnection->send(
                respond_html($this->getView($httpConnection, 'server.errors.404', ['subdomain' => $subdomain]), 404)
            );
            $httpConnection->close();

            return;
        }

        var_dump("3");
        $this->sendRequestToClient($request, $controlConnection, $httpConnection);
    }

    protected function detectSubdomain(Request $request): ?string
    {
        $subdomain = Str::before($request->getHost(), '.'.$this->configuration->hostname());

        return $subdomain === $request->getHost() ? null : $subdomain;
    }

    protected function sendRequestToClient(Request $request, ControlConnection $controlConnection, ConnectionInterface $httpConnection)
    {
        var_dump("4");
        $request = $this->prepareRequest($request, $controlConnection);

        $requestId = $request->header('X-Expose-Request-ID');

        $httpConnection = $this->connectionManager->storeHttpConnection($httpConnection, $requestId);

        var_dump("6");

        transform($this->passRequestThroughModifiers($request, $httpConnection), function (Request $request) use ($controlConnection, $httpConnection, $requestId) {
            var_dump("11");
            $controlConnection->once('proxy_ready_'.$requestId, function (ConnectionInterface $proxy) use ($request) {
                var_dump("12");
                // Convert the Laravel request into a PSR7 request
                $psr17Factory = new Psr17Factory();
                $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
                $request = $psrHttpFactory->createRequest($request);

                $binaryMsg = new Frame(str($request), true, Frame::OP_BINARY);

                var_dump("13");

                $proxy->send($binaryMsg);
            });

            $controlConnection->registerProxy($requestId);
        });
    }

    protected function passRequestThroughModifiers(Request $request, HttpConnection $httpConnection): ?Request
    {
        var_dump("7");
        foreach ($this->modifiers as $modifier) {
            var_dump("8");

            $request = app($modifier)->handle($request, $httpConnection);

            var_dump("9");
            if (is_null($request)) {
                break;
            }
        }


        var_dump("10");

        return $request;
    }

    protected function prepareRequest(Request $request, ControlConnection $controlConnection): Request
    {
        $request::setTrustedProxies([$controlConnection->socket->remoteAddress, '127.0.0.1'], Request::HEADER_X_FORWARDED_ALL);

        $host = $this->configuration->hostname();

        if (! $request->isSecure()) {
            $host .= ":{$this->configuration->port()}";
        }

        $request->headers->set('Host', $controlConnection->host);
        $request->headers->set('X-Forwarded-Proto', $request->isSecure() ? 'https' : 'http');
        $request->headers->set('X-Expose-Request-ID', uniqid());
        $request->headers->set('Upgrade-Insecure-Requests', 1);
        $request->headers->set('X-Exposed-By', config('app.name').' '.config('app.version'));
        $request->headers->set('X-Original-Host', "{$controlConnection->subdomain}.{$host}");

        var_dump("5");

        return $request;
    }
}
