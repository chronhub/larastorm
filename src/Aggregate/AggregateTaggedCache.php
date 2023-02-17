<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Aggregate;

use Illuminate\Contracts\Cache\Repository;
use Chronhub\Storm\Contracts\Aggregate\AggregateRoot;
use Chronhub\Storm\Contracts\Aggregate\AggregateCache;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;

final class AggregateTaggedCache implements AggregateCache
{
    private int $count = 0;

    public function __construct(private readonly Repository $cache,
                                public readonly string $cacheTag,
                                public readonly int $limit)
    {
    }

    public function put(AggregateRoot $aggregateRoot): void
    {
        if ($this->count === $this->limit) {
            $this->flush();
        }

        $aggregateId = $aggregateRoot->aggregateId();

        if (! $this->has($aggregateId)) {
            $this->count++;
        }

        $cacheKey = $this->determineCacheKey($aggregateId);

        $this->cache->tags([$this->cacheTag])->forever($cacheKey, $aggregateRoot);
    }

    public function get(AggregateIdentity $aggregateId): ?AggregateRoot
    {
        $cacheKey = $this->determineCacheKey($aggregateId);

        return $this->cache->tags([$this->cacheTag])->get($cacheKey);
    }

    public function forget(AggregateIdentity $aggregateId): void
    {
        if ($this->has($aggregateId)) {
            $cacheKey = $this->determineCacheKey($aggregateId);

            if ($this->cache->tags([$this->cacheTag])->forget($cacheKey)) {
                $this->count--;
            }
        }
    }

    public function flush(): void
    {
        $this->count = 0;

        $this->cache->tags([$this->cacheTag])->flush();
    }

    public function has(AggregateIdentity $aggregateId): bool
    {
        $cacheKey = $this->determineCacheKey($aggregateId);

        return $this->cache->tags([$this->cacheTag])->has($cacheKey);
    }

    public function count(): int
    {
        return $this->count;
    }

    private function determineCacheKey(AggregateIdentity $aggregateId): string
    {
        return class_basename($aggregateId::class).':'.$aggregateId->toString();
    }
}
