<?php

namespace Components\PHPSandbox\Commands;

use App\Commands\ServeCommand as OriginalServeCommand;

class ServeCommand extends OriginalServeCommand
{
    public function handle()
    {
        $this->setErrorReporting();

        parent::handle();
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
