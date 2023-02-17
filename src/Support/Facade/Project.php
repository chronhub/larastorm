<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Facade;

use Illuminate\Support\Facades\Facade;
use Chronhub\Storm\Projector\ProjectorManagerFactory;
use Chronhub\Storm\Contracts\Projector\ProjectorManager;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;

/**
 * @method static ProjectorManager create(string $name)
 * @method static ProjectorServiceManager extends(string $name, callable $callback)
 * @method static ProjectorServiceManager shouldUse(string $driver, string|ProjectorManagerFactory $factory)
 * @method static ProjectorServiceManager setDefaultDriver(string $driver)
 * @method static string getDefaultDriver()
 */
final class Project extends Facade
{
    public const SERVICE_ID = 'projector.service_manager';

    protected static function getFacadeAccessor(): string
    {
        return self::SERVICE_ID;
    }
}
