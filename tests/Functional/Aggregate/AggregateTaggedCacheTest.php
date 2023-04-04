<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Aggregate;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Repository;
use Chronhub\Storm\Aggregate\V4AggregateId;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Storm\Contracts\Aggregate\AggregateRoot;
use Chronhub\Larastorm\Aggregate\AggregateTaggedCache;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;

#[CoversClass(AggregateTaggedCache::class)]
final class AggregateTaggedCacheTest extends OrchestraTestCase
{
    private AggregateIdentity $aggregateId;

    private AggregateRoot $aggregateRoot;

    private Repository $cache;

    public function setUp(): void
    {
        parent::setUp();

        $this->aggregateId = V4AggregateId::create();
        $this->aggregateRoot = AggregateRootStub::create($this->aggregateId);
        $this->cache = Cache::store();
    }

    public function testInstanceWithEmptyCache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());
    }

    public function testAggregateIsCached(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $cache->put($this->aggregateRoot);

        $this->assertTrue($cache->has($this->aggregateId));
    }

    public function testAggregateIsNotCached(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertFalse($cache->has($this->aggregateId));
    }

    public function testCacheAggregateAndIncrementCounter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());
    }

    public function testOverrideAggregateInCacheAndDoesNotIncrementCounter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());
    }

    public function testGetAggregateFromCache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $cache->put($this->aggregateRoot);

        $this->assertEquals($this->aggregateRoot, $cache->get($this->aggregateId));
    }

    public function testGetNullAggregateWhichWasNotCached(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertNull($cache->get($this->aggregateId));
    }

    public function testFlushCache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->flush();

        $this->assertEquals(0, $cache->count());
    }

    public function testForgetAggregateAndDecrementCounter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->forget($this->aggregateId);

        $this->assertEquals(0, $cache->count());
    }

    public function testFlushCacheWhenMaxSizeIsReached(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 1);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());
    }
}
