<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Chronhub\Storm\Aggregate\NullAggregateCache;
use Chronhub\Storm\Contracts\Aggregate\AggregateCache;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use function class_exists;

final class AggregateCacheFactory
{
    /**
     * @param  class-string  $aggregateRoot
     * @param  array{"size"?: int, "tag"?: string ,"driver"?: string}  $cacheConfig
     *
     * @throws InvalidArgumentException when string aggregate root is not a valid class name
     */
    public function createCache(string $aggregateRoot, array $cacheConfig): AggregateCache
    {
        if (! class_exists($aggregateRoot)) {
            throw new InvalidArgumentException('String aggregate root must be a valid class name');
        }

        if (($cacheConfig['size'] ?? 0) === 0) {
            return new NullAggregateCache();
        }

        return new AggregateTaggedCache(
            Cache::store($cacheConfig['driver'] ?? null),
            $cacheConfig['tag'] ?? 'identity-'.Str::snake(class_basename($aggregateRoot)),
            $cacheConfig['size']
        );
    }
}
