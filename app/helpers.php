<?php

use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\str;

function respond_json($responseData, int $statusCode = 200, array $headers = [])
{
    return str(new Response(
        $statusCode,
        array_merge($headers, ['Content-Type' => 'application/json']),
        json_encode($responseData, JSON_INVALID_UTF8_IGNORE)
    ));
}

function respond_html(string $html, int $statusCode = 200, array $headers = [])
{
    return str(new Response(
        $statusCode,
        array_merge($headers, ['Content-Type' => 'text/html']),
        $html
    ));
}
