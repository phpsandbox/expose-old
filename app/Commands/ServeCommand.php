<?php

namespace App\Commands;

use App\Server\Factory;
use LaravelZero\Framework\Commands\Command;
use React\EventLoop\LoopInterface;

class ServeCommand extends Command
{
    protected $signature = 'serve {hostname=localhost} {host=0.0.0.0}  {--validateAuthTokens} {--port=8080}';

    protected $description = 'Start the expose server';

    public function handle()
    {
        /** @var LoopInterface $loop */
        $loop = app(LoopInterface::class);

        $this->setErrorReporting();

        $loop->futureTick(function () {
            $this->info('Expose server running on port '.$this->option('port').'.');
        });

        $validateAuthTokens = config('expose.admin.validate_auth_tokens');

        if ($this->option('validateAuthTokens') === true) {
            $validateAuthTokens = true;
        }

        (new Factory())
            ->setLoop($loop)
            ->setHost($this->argument('host'))
            ->setPort($this->option('port'))
            ->setHostname($this->argument('hostname'))
            ->validateAuthTokens($validateAuthTokens)
            ->createServer()
            ->run();
    }

    private function setErrorReporting(): void
    {
        /**
         * This is being applied specifically as a workaround of
         * https://github.com/reactphp/promise/blob/0845d291435b862f9a22bb5971ba18f50a00da09/src/functions.php#L345
         * which throws E_DEPRECATED ErrorException since PHP8 as documented here:
         * https://www.php.net/manual/en/migration80.deprecated.php#migration80.deprecated.reflection
         *
         * Also see https://www.php.net/manual/en/migration80.incompatible.php
         *
         * The solution disables reporting E_DEPRECATED.
         *
         * We will keep looking around for a less dangerous solution.
         */
        error_reporting(error_reporting() & ~E_DEPRECATED);
    }
}
