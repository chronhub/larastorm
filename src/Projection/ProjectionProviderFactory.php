<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Projection;

use Closure;
use Illuminate\Contracts\Container\Container;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider as Provider;
use function is_string;

final readonly class ProjectionProviderFactory
{
    private Container $container;

    public function __construct(Closure $container)
    {
        $this->container = $container();
    }

    public function createProvider(string|array $provider): Provider
    {
        if (is_string($provider)) {
            return $this->container[$provider];
        }

        $connectionName = $provider['name'] ?? null;

        if ($connectionName === null) {
            throw new InvalidArgumentException('Projection provider connection name is not defined');
        }

        return new ProjectionProvider(
            $this->container['db']->connection($connectionName),
            $provider['table'] ?? null,
        );
    }
}
