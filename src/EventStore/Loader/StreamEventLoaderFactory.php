<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Loader;

use Illuminate\Contracts\Container\Container;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use function explode;
use function str_starts_with;

class StreamEventLoaderFactory
{
    public function __construct(protected readonly Container $container)
    {
    }

    public function __invoke(?string $configKey): StreamEventLoaderConnection
    {
        if ($configKey === 'cursor' || $configKey === null) {
            return $this->container[CursorQueryLoader::class];
        }

        if ($configKey === 'lazy') {
            return $this->container[LazyQueryLoader::class];
        }

        if (str_starts_with($configKey, 'lazy:')) {
            $chunkSize = (int) explode(':', $configKey)[1];

            return new LazyQueryLoader($this->container[StreamEventLoader::class], $chunkSize);
        }

        return $this->container[$configKey];
    }
}
