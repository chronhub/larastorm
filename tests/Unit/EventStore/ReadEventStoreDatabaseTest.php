<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Chronhub\Larastorm\EventStore\Database\AbstractEventStoreDatabase;
use function iterator_to_array;

#[CoversClass(EventStoreDatabase::class)]
#[CoversClass(AbstractEventStoreDatabase::class)]
final class ReadEventStoreDatabaseTest extends UnitTestCase
{
    use ProvideTestingStore;

    #[DataProvider('provideDirection')]
    public function testRetrieveAllStreamWithSingleStrategy(string $direction): void
    {
        $tableName = 'read_customer';
        $builder = $this->createMock(Builder::class);
        $aggregateId = $this->createMock(AggregateIdentity::class);

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $this->streamPersistence->expects($this->once())
            ->method('isAutoIncremented')
            ->willReturn(false);

        $builder->expects($this->once())
            ->method('orderBy')
            ->with('no', $direction)
            ->willReturn($builder);

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());

        $this->eventLoader->expects($this->once())
            ->method('query')
            ->with($builder, $this->streamName)
            ->will($this->returnCallback(function () use ($expectedStreamsEvents) {
                yield from $expectedStreamsEvents;
            }));

        $aggregateId->expects($this->never())->method('toString');

        $events = $this->eventStore()->retrieveAll($this->streamName, $aggregateId, $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    #[DataProvider('provideDirection')]
    public function testRetrieveAllStreamWithSingleStrategyWithQueryHint(string $direction): void
    {
        $tableName = 'read_customer';
        $indexName = 'ix_foo';
        $builder = $this->createMock(Builder::class);
        $aggregateId = $this->createMock(AggregateIdentity::class);

        $streamPersistence = $this->createMock(StreamPersistenceWithQueryHint::class);

        $streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $streamPersistence->expects($this->once())
            ->method('indexName')
            ->with($tableName)
            ->willReturn($indexName);

        $this->connection->expects($this->once())
            ->method('query')
            ->willReturn($builder);

        $builder->expects($this->once())
            ->method('fromRaw')
            ->with("`$tableName` USE INDEX($indexName)")
            ->willReturn($builder);

        $streamPersistence->expects($this->once())
            ->method('isAutoIncremented')
            ->willReturn(false);

        $builder->expects($this->once())
            ->method('orderBy')
            ->with('no', $direction)
            ->willReturn($builder);

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());

        $this->eventLoader->expects($this->once())
            ->method('query')
            ->with($builder, $this->streamName)
            ->will($this->returnCallback(function () use ($expectedStreamsEvents) {
                yield from $expectedStreamsEvents;
            }));

        $aggregateId->expects($this->never())->method('toString');

        $events = $this->eventStore(null, $streamPersistence)->retrieveAll($this->streamName, $aggregateId, $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    #[DataProvider('provideDirection')]
    public function testRetrieveAllStreamWithPerAggregateStrategy(string $direction): void
    {
        $tableName = 'read_customer';
        $builder = $this->createMock(Builder::class);
        $aggregateId = $this->createMock(AggregateIdentity::class);

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $this->streamPersistence->expects($this->once())
            ->method('isAutoIncremented')
            ->willReturn(true);

        $builder->expects($this->once())
            ->method('where')
            ->with('aggregate_id', '123-456')
            ->willReturn($builder);

        $builder->expects($this->once())
            ->method('orderBy')
            ->with('no', $direction)
            ->willReturn($builder);

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());

        $this->eventLoader->expects($this->once())
            ->method('query')
            ->with($builder, $this->streamName)
            ->will($this->returnCallback(function () use ($expectedStreamsEvents) {
                yield from $expectedStreamsEvents;
            }));

        $aggregateId->expects($this->once())
            ->method('toString')
            ->willReturn('123-456');

        $events = $this->eventStore()->retrieveAll($this->streamName, $aggregateId, $direction);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    public function testRetrieveFilteredStreamEvents(): void
    {
        $tableName = 'read_customer';
        $builder = $this->createMock(Builder::class);
        $queryFilter = $this->createMock(QueryFilter::class);

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $this->streamPersistence->expects($this->never())->method('isAutoIncremented');

        $expectedStreamsEvents = iterator_to_array($this->provideStreamEvents());

        $this->eventLoader->expects($this->once())
            ->method('query')
            ->with($builder, $this->streamName)
            ->will($this->returnCallback(function () use ($expectedStreamsEvents) {
                yield from $expectedStreamsEvents;
            }));

        $callback = function (Builder $query) use ($builder): void {
            $this->assertSame($query, $builder);
        };

        $queryFilter->expects($this->once())
            ->method('apply')
            ->willReturn($callback);

        $events = $this->eventStore()->retrieveFiltered($this->streamName, $queryFilter);

        $this->assertEquals($expectedStreamsEvents, iterator_to_array($events));
    }

    public function testFilterStreamNames(): void
    {
        $streams = [new StreamName('foo'), new StreamName('bar'), new StreamName('foo_bar')];

        $this->eventStreamProvider->expects($this->once())
            ->method('filterByStreams')
            ->with($streams)
            ->willReturn(['foo', 'bar']);

        $filteredStreams = $this->eventStore()->filterStreamNames(...$streams);

        $this->assertEquals([$streams[0], $streams[1]], $filteredStreams);
    }

    public function testFilterCategories(): void
    {
        $categories = ['foo', 'bar', 'foo_bar'];

        $this->eventStreamProvider->expects($this->once())
            ->method('filterByCategories')
            ->with($categories)
            ->willReturn(['foo', 'bar']);

        $filteredStreams = $this->eventStore()->filterCategoryNames(...$categories);

        $this->assertEquals([$categories[0], $categories[1]], $filteredStreams);
    }

    #[DataProvider('provideBoolean')]
    public function testCheckStreamExists(bool $streamExists): void
    {
        $this->eventStreamProvider->expects($this->once())
            ->method('hasRealStreamName')
            ->with($this->streamName->name)
            ->willReturn($streamExists);

        $this->assertEquals($streamExists, $this->eventStore()->hasStream($this->streamName));
    }

    public function testReadQueryBuilder(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $builder = $this->createMock(Builder::class);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $queryBuilder = $this->eventStore()->getBuilderforRead($this->streamName);

        $this->assertSame($builder, $queryBuilder);
    }
}
