<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use Chronhub\Storm\Contracts\Reporter\Reporter;
use Illuminate\Support\Facades\Facade;

/**
 * @method static Reporter create(string $type, string $name)
 * @method static Reporter command(string $name='default')
 * @method static Reporter event(string $name='default')
 * @method static Reporter query(string $name='default')
 */
final class Report extends Facade
{
    public const SERVICE_ID = 'cqrs.manager';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
