<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Contracts;

use Chronhub\Storm\Contracts\Aggregate\AggregateRepository;
use Illuminate\Contracts\Container\Container;

interface AggregateRepositoryManager
{
    /**
     * @param non-empty-string $streamName
     */
    public function create(string $streamName): AggregateRepository;

    /**
     * @param callable(Container, non-empty-string, array): AggregateRepository $aggregateRepository
     * @param non-empty-string                                                  $streamName
     */
    public function extends(string $streamName, callable $aggregateRepository): void;
}
