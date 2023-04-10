<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider as Provider;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Connection;
use function is_array;

class EventStreamProviderFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    public function createProvider(Connection $connection, ?string $providerKey): Provider
    {
        if ($providerKey === null) {
            $providerKey = 'connection';
        }

        $provider = $this->container['config']['chronicler.defaults.event_stream_provider'][$providerKey] ?? null;

        if ($provider === null || is_array($provider)) {
            $tableName = $provider['table_name'] ?? null;

            //checkMe do we need to check if the connection name match one in config?

            return new EventStreamProvider($connection, $tableName);
        }

        return $this->container[$providerKey];
    }
}
