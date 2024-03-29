<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Illuminate\Contracts\Container\Container;
use function explode;
use function str_starts_with;

class EventLoaderConnectionFactory
{
    public function __construct(protected readonly Container $container)
    {
    }

    public function createEventLoader(?string $name): StreamEventLoaderConnection
    {
        if ($name === null || $name === 'cursor') {
            return $this->container[CursorQueryLoader::class];
        }

        if (str_starts_with($name, 'lazy:')) {
            return new LazyQueryLoader($this->container[EventLoader::class], (int) explode(':', $name)[1]);
        }

        if ($name === 'lazy') {
            return $this->container[LazyQueryLoader::class];
        }

        return $this->container[$name];
    }
}
