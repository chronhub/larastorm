<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Aggregate;

use Chronhub\Larastorm\Aggregate\AggregateCacheFactory;
use Chronhub\Larastorm\Aggregate\AggregateTaggedCache;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Storm\Aggregate\NullAggregateCache;
use Generator;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(AggregateCacheFactory::class)]
final class AggregateCacheFactoryTest extends OrchestraTestCase
{
    #[Test]
    public function it_assert_instance(): void
    {
        $factory = new AggregateCacheFactory();

        $aggregateCache = $factory->createCache(AggregateRootStub::class, 10);

        $this->assertInstanceOf(AggregateTaggedCache::class, $aggregateCache);

        $this->assertEquals('aggregate-aggregate_root_stub', $aggregateCache->tag);
        $this->assertEquals(10, $aggregateCache->limit);
    }

    #[Test]
    public function it_test_cache_tag(): void
    {
        $factory = new AggregateCacheFactory();

        $aggregateCache = $factory->createCache(AggregateRootStub::class, 1000, 'my_tag');

        $this->assertInstanceOf(AggregateTaggedCache::class, $aggregateCache);

        $this->assertEquals(1000, $aggregateCache->limit);
        $this->assertEquals('my_tag', $aggregateCache->tag);
    }

    #[Test]
    public function it_test_cache_driver(): void
    {
        Cache::expects('store')->with('redis')->andReturn($this->createMock(Repository::class));

        $factory = new AggregateCacheFactory();

        $factory->createCache(AggregateRootStub::class, 1, null, 'redis');
    }

    #[DataProvider('provideValuesForNullAggregateCache')]
    #[Test]
    public function it_return_null_aggregate_cache(?int $cacheSize): void
    {
        $factory = new AggregateCacheFactory();

        $aggregateCache = $factory->createCache(AggregateRootStub::class, $cacheSize);

        $this->assertEquals(NullAggregateCache::class, $aggregateCache::class);
    }

    public static function provideValuesForNullAggregateCache(): Generator
    {
        yield [null];
        yield [0];
    }
}
