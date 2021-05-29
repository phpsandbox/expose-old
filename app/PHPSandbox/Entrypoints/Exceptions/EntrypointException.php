<?php

namespace App\PHPSandbox\Entrypoints\Exceptions;

use Exception;

class EntrypointException extends Exception
{
    public static function notFound(string $entrypoint): EntrypointException
    {
        return new static("Entrypoint `$entrypoint` not found.", 404);
    }
}
