<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use Illuminate\Support\Facades\Facade;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerFactory;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;

/**
 * @method static Chronicler create(string $name)
 * @method static ChroniclerManager extend(string $name, callable $callback)
 * @method static ChroniclerManager shouldUse(string $driver, string|ChroniclerFactory $factory)
 * @method static ChroniclerManager setDefaultDriver(string $driver)
 * @method static string getDefaultDriver()
 */
final class Chronicle extends Facade
{
    public const SERVICE_ID = 'chronicler.manager';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
