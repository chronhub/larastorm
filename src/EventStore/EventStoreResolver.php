<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Illuminate\Contracts\Container\Container;
use function is_string;

final readonly class EventStoreResolver
{
    private Container $container;

    public function __construct(callable $container)
    {
        $this->container = $container();
    }

    /**
     * @param string|array{string, string} $eventStore
     */
    public function resolve(string|array $eventStore): Chronicler
    {
        if (is_string($eventStore)) {
            return $this->container[$eventStore];
        }

        [$eventStoreDriver, $eventStoreName] = $eventStore;

        return $this->container[ChroniclerManager::class]->setDefaultDriver($eventStoreDriver)->create($eventStoreName);
    }
}
