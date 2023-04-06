<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\ReadModel;

use Illuminate\Database\Query\Builder;
use function abs;

abstract class AbstractQueryModelConnection extends AbstractReadModelConnection
{
    protected function insert(array $data): void
    {
        $this->query()->insert($data);
    }

    protected function update(string $key, array $data): void
    {
        $this->query()->where($this->getKey(), $key)->update($data);
    }

    protected function increment(string $key, string $column, int $amount = 1, array $extra = []): void
    {
        $this->query()->where($this->getKey(), $key)->increment($column, abs($amount), $extra);
    }

    protected function incrementEach(string $key, array $columns, array $extra = []): void
    {
        $this->query()->where($this->getKey(), $key)->incrementEach($columns, $extra);
    }

    protected function decrement(string $key, string $column, int $amount = 1, array $extra = []): void
    {
        $this->query()->where($this->getKey(), $key)->decrement($column, abs($amount), $extra);
    }

    protected function decrementEach(string $key, array $columns, array $extra = []): void
    {
        $this->query()->where($this->getKey(), $key)->decrementEach($columns, $extra);
    }

    protected function delete(string $key): void
    {
        $this->query()->where($this->getKey(), $key)->delete();
    }

    protected function query(): Builder
    {
        return $this->connection->table($this->tableName());
    }

    protected function getKey(): string
    {
        return 'id';
    }
}
