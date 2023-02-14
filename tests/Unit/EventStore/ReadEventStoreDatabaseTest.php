<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use function iterator_to_array;

final class ReadEventStoreDatabaseTest extends ProphecyTestCase
{
    use ProvideTestingStore;

    /**
     * @test
     *
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events_with_single_stream_strategy(string $direction): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();
        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $this->streamPersistence->isAutoIncremented()->willReturn(false)->shouldBeCalledOnce();

        $builder->orderBy('no', $direction)->willReturn($builder)->shouldBeCalledOnce();

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());
        $this->eventLoader->query($builder->reveal(), $this->streamName)->willYield($expectedStreamsEvents)->shouldBeCalledOnce();

        $aggregateId = $this->prophesize(AggregateIdentity::class);
        $aggregateId->toString()->willReturn('123-456')->shouldNotBeCalled();

        $events = $this->eventStore()->retrieveAll($this->streamName, $aggregateId->reveal(), $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    /**
     * @test
     *
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events_with_one_stream_per_aggregate_strategy(string $direction): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();
        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $this->streamPersistence->isAutoIncremented()->willReturn(true)->shouldBeCalledOnce();

        $builder->where('aggregate_id', '123-456')->willReturn($builder)->shouldBeCalledOnce();
        $builder->orderBy('no', $direction)->willReturn($builder)->shouldBeCalledOnce();

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());
        $this->eventLoader->query($builder->reveal(), $this->streamName)->willYield($expectedStreamsEvents)->shouldBeCalledOnce();

        $aggregateId = $this->prophesize(AggregateIdentity::class);
        $aggregateId->toString()->willReturn('123-456')->shouldBeCalledOnce();

        $events = $this->eventStore()->retrieveAll($this->streamName, $aggregateId->reveal(), $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    /**
     * @test
     */
    public function it_retrieve_filtered_stream_events(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();
        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $this->streamPersistence->isAutoIncremented()->shouldNotBeCalled();

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());
        $this->eventLoader->query($builder->reveal(), $this->streamName)->willYield($expectedStreamsEvents)->shouldBeCalledOnce();

        $callback = function (Builder $query) use ($builder): void {
            $this->assertSame($query, $builder->reveal());
        };

        $queryFilter = $this->prophesize(QueryFilter::class);
        $queryFilter->apply()->willReturn($callback)->shouldBeCalledOnce();

        $events = $this->eventStore()->retrieveFiltered($this->streamName, $queryFilter->reveal());

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    /**
     * @test
     */
    public function it_filter_stream_names(): void
    {
        $streams = [new StreamName('foo'), new StreamName('bar'), new StreamName('foo_bar')];

        $this->eventStreamProvider->filterByStreams($streams)->willReturn(['foo', 'bar'])->shouldBeCalledOnce();

        $filteredStreams = $this->eventStore()->filterStreamNames(...$streams);

        $this->assertEquals([$streams[0], $streams[1]], $filteredStreams);
    }

    /**
     * @test
     */
    public function it_filter_categories(): void
    {
        $categories = ['foo', 'bar', 'foo_bar'];

        $this->eventStreamProvider->filterByCategories($categories)->willReturn(['foo', 'bar'])->shouldBeCalledOnce();

        $filteredStreams = $this->eventStore()->filterCategoryNames(...$categories);

        $this->assertEquals([$categories[0], $categories[1]], $filteredStreams);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_check_stream_exists(bool $streamExists): void
    {
        $this->eventStreamProvider->hasRealStreamName($this->streamName->name)->willReturn($streamExists)->shouldBeCalledOnce();

        $this->assertEquals($streamExists, $this->eventStore()->hasStream($this->streamName));
    }

    /**
     * @test
     */
    public function it_assert_read_query_builder(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn(null)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->table($tableName)->willReturn($builder->reveal())->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore()->getBuilderforRead($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }

    /**
     * @test
     */
    public function it_assert_read_query_builder_with_index(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamPersistence->indexName($tableName)->willReturn('some_index')->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->query()->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->fromRaw("`$tableName` USE INDEX(some_index)")->willReturn($builder->reveal())->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore()->getBuilderforRead($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }
}
