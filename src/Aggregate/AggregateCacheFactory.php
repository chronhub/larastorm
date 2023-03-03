<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Chronhub\Storm\Aggregate\NullAggregateCache;
use Chronhub\Storm\Contracts\Aggregate\AggregateCache;

final class AggregateCacheFactory
{
    /**
     * @param  class-string  $aggregateRoot
     */
    public function createCache(string $aggregateRoot,
                                ?int $size,
                                ?string $tag = null,
                                ?string $driver = null): AggregateCache
    {
        if ($size === null || $size === 0) {
            return new NullAggregateCache();
        }

        $store = Cache::store($driver ?? null);

        $tag ??= 'identity-'.Str::snake(class_basename($aggregateRoot));

        return new AggregateTaggedCache($store, $tag, $size);
    }
}
