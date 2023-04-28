<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Chronhub\Storm\Aggregate\NullAggregateCache;
use Chronhub\Storm\Contracts\Aggregate\AggregateCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class AggregateCacheFactory
{
    /**
     * @param class-string          $aggregateRoot
     * @param int<0, max>|null      $size
     * @param non-empty-string|null $tag
     * @param non-empty-string|null $driver
     */
    public function createCache(
        string $aggregateRoot,
        ?int $size,
        ?string $tag = null,
        ?string $driver = null
    ): AggregateCache {
        if ($size === null || $size === 0) {
            return new NullAggregateCache();
        }

        $store = Cache::store($driver ?? null);

        $tag ??= 'aggregate-'.Str::snake(class_basename($aggregateRoot));

        return new AggregateTaggedCache($store, $tag, $size);
    }
}
