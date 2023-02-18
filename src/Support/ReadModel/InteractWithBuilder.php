<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\ReadModel;

use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Reporter\DomainEvent;

trait InteractWithBuilder
{
    /**
     * Write query with a given callback
     *
     * eg: $this->readModel()->stack(
     *          'query', function(Builder $query, string $key, DomainEvent $event): void{
     *              $query->insert[*];
     *      ), $event);
     */
    protected function query(callable $callback, ?DomainEvent $event = null): void
    {
        $callback($this->queryBuilder(), $this->getKey(), $event);
    }

    protected function queryBuilder(): Builder
    {
        return $this->connection->table($this->tableName());
    }

    protected function getKey(): string
    {
        return 'id';
    }
}
