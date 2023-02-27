<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor;

use Symfony\Component\Process\PhpExecutableFinder;

class PhpBinary
{
    public static function path(): string
    {
        $binary = new PhpExecutableFinder();

        return $binary->find(false);
    }
}
