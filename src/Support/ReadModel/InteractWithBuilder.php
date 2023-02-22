<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\ReadModel;

use Illuminate\Database\Query\Builder;
use Chronhub\Storm\Reporter\DomainEvent;

trait InteractWithBuilder
{
    /**
     * @param  callable(Builder, string, DomainEvent): void  $callback
     *
     * @example $this->readModel()->stack(
     *          'query', function(Builder $query, string $key, DomainEvent $event): void{
     *              $query->insert[
     *                  $key => $event->aggregateId()->toString(),
     *                  'email' => $event->customerEmail()->value
     *              ];
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
