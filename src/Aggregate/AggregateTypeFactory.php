<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Storm\Aggregate\AggregateType;
use Chronhub\Storm\Contracts\Aggregate\AggregateRoot;
use Chronhub\Storm\Contracts\Aggregate\AggregateType as Type;
use Illuminate\Contracts\Container\Container;
use function is_a;
use function is_string;

final readonly class AggregateTypeFactory
{
    private Container $container;

    public function __construct(callable $container)
    {
        $this->container = $container();
    }

    /**
     * @param string|array{"root": class-string, "lineage": array{class-string}} $aggregateType
     */
    public function createType(string|array $aggregateType): Type
    {
        if (is_string($aggregateType)) {
            if (is_a($aggregateType, AggregateRoot::class, true)) {
                return new AggregateType($aggregateType);
            }

            return $this->container[$aggregateType];
        }

        return new AggregateType($aggregateType['root'], $aggregateType['lineage']);
    }
}
