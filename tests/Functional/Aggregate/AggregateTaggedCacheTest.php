<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Aggregate;

use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function it_can_be_instantiated_with_empty_cache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());
    }

    #[Test]
    public function it_assert_aggregate_exists_in_cache_by_aggregate_id(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $cache->put($this->aggregateRoot);

        $this->assertTrue($cache->has($this->aggregateId));
    }

    #[Test]
    public function it_assert_aggregate_does_not_exists_in_cache_by_aggregate_id(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertFalse($cache->has($this->aggregateId));
    }

    #[Test]
    public function it_put_aggregate_in_cache_and_increment_counter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());
    }

    #[Test]
    public function it_override_aggregate_in_cache_and_does_not_increment_counter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());
    }

    #[Test]
    public function it_return_aggregate_from_cache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $cache->put($this->aggregateRoot);

        $this->assertEquals($this->aggregateRoot, $cache->get($this->aggregateId));
    }

    #[Test]
    public function it_return_null_aggregate_from_cache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertNull($cache->get($this->aggregateId));
    }

    #[Test]
    public function it_flush_cache(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->flush();

        $this->assertEquals(0, $cache->count());
    }

    #[Test]
    public function it_remove_aggregate_from_cache_and_decrement_counter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 2);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->forget($this->aggregateId);

        $this->assertEquals(0, $cache->count());
    }

    #[Test]
    public function it_flush_cache_when_limit_is_hit_and_reset_counter(): void
    {
        $cache = new AggregateTaggedCache($this->cache, 'operation-', 1);

        $this->assertEquals(0, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());

        $cache->put($this->aggregateRoot);

        $this->assertEquals(1, $cache->count());
    }
}
