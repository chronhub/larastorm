<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ProjectorManagerInterface create(string $name)
 * @method static ProjectorServiceManager   extend(string $name, callable $callback)
 * @method static ProjectorServiceManager   setDefaultDriver(string $driver)
 * @method static string                    getDefaultDriver()
 */
final class Project extends Facade
{
    public const SERVICE_ID = 'projector.service_manager';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
