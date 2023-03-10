<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Persistence;

use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider as Provider;
use function is_array;

class EventStreamProviderFactory
{
    public function __construct(private readonly Container $container)
    {
    }

    public function createProvider(Connection $connection, ?string $provider): Provider
    {
        if ($provider === null) {
            $provider = 'connection';
        }

        $providerKey = $this->container['config']['chronicler.defaults.event_stream_provider'][$provider] ?? null;

        if ($providerKey === null || is_array($providerKey)) {
            $tableName = $providerKey['table_name'] ?? null;

            return new EventStreamProvider($connection, $tableName);
        }

        return $this->container[$providerKey];
    }
}
