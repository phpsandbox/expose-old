<?php

return [
    'ws_entrypoint' => [
        'basic_auth_username' => env('WS_ENTRYPOINT_BASIC_AUTH_USERNAME'),
        'basic_auth_password' => env('WS_ENTRYPOINT_BASIC_AUTH_PASSWORD'),
        'base_url' => env('WS_ENTRYPOINT_BASE_URL', 'http://phpsandbox.test/socket'),
        'timeout' => 60,
        'retries' => 2,
    ],
    'core_entrypoint' => [
        'basic_auth_username' => env('ENTRYPOINT_BASIC_AUTH_USERNAME'),
        'basic_auth_password' => env('ENTRYPOINT_BASIC_AUTH_PASSWORD'),
        'base_url' => env('ENTRYPOINT_BASE_URL', 'http://phpsandbox.test'),
    ],
    'notebooks' => [
        'autostart_enabled' => env('NOTEBOOK_AUTO_START', false),
    ],
];
