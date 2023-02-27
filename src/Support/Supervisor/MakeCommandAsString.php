<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Supervisor;

use function str_replace;

final class MakeCommandAsString
{
    public static string $command = 'exec @php artisan @namespace:@command';

    public static function toCommandString($namespace, $command): string
    {
        return str_replace(
            ['@php', '@namespace', '@command'],
            [PhpBinary::path(), $namespace, $command],
            self::$command
        );
    }
}
