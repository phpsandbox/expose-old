<?php

namespace App\Server\Http\Controllers\Admin;

use App\Contracts\ConnectionManager;
use App\Server\Configuration;
use Illuminate\Http\Request;
use Ratchet\ConnectionInterface;

class GetSiteController extends AdminController
{
    /** @var ConnectionManager */
    protected $connectionManager;

    /** @var Configuration */
    protected $configuration;

    public function __construct(ConnectionManager $connectionManager)
    {
        $this->connectionManager = $connectionManager;
    }

    public function handle(Request $request, ConnectionInterface $httpConnection)
    {
        $connection = $this->connectionManager->findControlConnectionForSubdomain($request->get('subdomain'));

        if (! is_null($connection)) {
            $connection->close();

            $this->connectionManager->removeControlConnection($connection);
        }

        $httpConnection->send(respond_json([
            'sites' => $this->connectionManager->getConnections(),
        ]));
    }
}
