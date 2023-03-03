<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Aggregate;

use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Aggregate\NullAggregateCache;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Aggregate\AggregateTaggedCache;
use Chronhub\Larastorm\Aggregate\AggregateCacheFactory;

final class AggregateCacheFactoryTest extends OrchestraTestCase
{
    /**
     * @test
     */
    public function it_assert_instance(): void
    {
        $factory = new AggregateCacheFactory();

        $aggregateCache = $factory->createCache(AggregateRootStub::class, 10);

        $this->assertInstanceOf(AggregateTaggedCache::class, $aggregateCache);

        $this->assertEquals('identity-aggregate_root_stub', $aggregateCache->cacheTag);
        $this->assertEquals(10, $aggregateCache->limit);
    }

    /**
     * @test
     */
    public function it_test_cache_tag(): void
    {
        $factory = new AggregateCacheFactory();

        $aggregateCache = $factory->createCache(
            AggregateRootStub::class,
            1000,
            'my_tag'
        );

        $this->assertInstanceOf(AggregateTaggedCache::class, $aggregateCache);

        $this->assertEquals(1000, $aggregateCache->limit);
        $this->assertEquals('my_tag', $aggregateCache->cacheTag);
    }

    /**
     * @test
     */
    public function it_test_cache_driver(): void
    {
        Cache::expects('store')->with('redis')->andReturn($this->createMock(Repository::class));

        $factory = new AggregateCacheFactory();

        $factory->createCache(AggregateRootStub::class,
            1,
            null,
            'redis'
        );
    }

    /**
     * @test
     *
     * @dataProvider provideValuesForNullAggregateCache
     */
    public function it_return_null_aggregate_cache(?int $cacheSize): void
    {
        $factory = new AggregateCacheFactory();

        $aggregateCache = $factory->createCache(AggregateRootStub::class, $cacheSize);

        $this->assertEquals(NullAggregateCache::class, $aggregateCache::class);
    }

    public function provideValuesForNullAggregateCache(): Generator
    {
        yield [null];
        yield [0];
    }
}
