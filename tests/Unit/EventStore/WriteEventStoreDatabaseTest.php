<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Database\AbstractEventStoreDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Stream\Stream;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Throwable;
use function iterator_to_array;

#[CoversClass(EventStoreDatabase::class)]
#[CoversClass(AbstractEventStoreDatabase::class)]
final class WriteEventStoreDatabaseTest extends UnitTestCase
{
    use ProvideTestingStore;

    #[DataProvider('provideWriteLock')]
    public function testWriteQueryBuilder(?WriteLockStrategy $writeLock = null): void
    {
        $tableName = 'read_customer';
        $builder = $this->createMock(Builder::class);

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $builder->expects($this->once())
            ->method('useWritePdo')
            ->willReturn($builder);

        $queryBuilder = $this->eventStore($writeLock)->getBuilderforWrite($this->streamName);

        $this->assertSame($builder, $queryBuilder);
    }

    public function testWriteQueryBuilderWithMysqlWriteLock(): void
    {
        $tableName = 'read_customer';
        $builder = $this->createMock(Builder::class);

        $builder->expects($this->once())
            ->method('useWritePdo')
            ->willReturn($builder);

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $builder->expects($this->once())
            ->method('lockForUpdate')
            ->willReturn($builder);

        $queryBuilder = $this->eventStore(new MysqlWriteLock())->getBuilderforWrite($this->streamName);

        $this->assertSame($builder, $queryBuilder);
    }

    public function testCreateFirstCommit(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->expects($this->exactly(2))
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->streamCategory->expects($this->once())
            ->method('determineFrom')
            ->with($this->streamName->name)
            ->willReturn(null);

        $this->eventStreamProvider->expects($this->once())
            ->method('createStream')
            ->with($this->streamName->name, $tableName, null)
            ->willReturn(true);

        $this->streamPersistence->expects($this->once())
            ->method('up')
            ->with($tableName)
            ->willReturn(null);

        $eventStore = $this->eventStore();

        $eventStore->firstCommit(new Stream($this->streamName));
    }

    public function testCreateFirstCommitWithCategory(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->exactly(2))
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->streamCategory->expects($this->once())
            ->method('determineFrom')
            ->with($this->streamName->name)
            ->willReturn('admin');

        $this->eventStreamProvider->expects($this->once())
            ->method('createStream')
            ->with($this->streamName->name, $tableName, 'admin')
            ->willReturn(true);

        $this->streamPersistence->expects($this->once())
            ->method('up')
            ->with($tableName)
            ->willReturn(null);

        $eventStore = $this->eventStore();

        $eventStore->firstCommit(new Stream($this->streamName));
    }

    public function testExceptionRaisedWhenCreateFailed(): void
    {
        $this->expectException(ConnectionQueryFailure::class);
        $this->expectExceptionMessage("Unable to insert data for stream {$this->streamName->name} in event stream table");

        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->streamCategory->expects($this->once())
            ->method('determineFrom')
            ->with($this->streamName->name)
            ->willReturn(null);

        $this->eventStreamProvider->expects($this->once())
            ->method('createStream')
            ->with($this->streamName->name, $tableName, null)
            ->willReturn(false);

        $this->streamPersistence->expects($this->never())->method('up');

        $this->eventStore()->firstCommit(new Stream($this->streamName));
    }

    public function testExceptionRaisedWhenUpStreamFailed(): void
    {
        $this->expectException(QueryException::class);

        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->exactly(2))
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->streamCategory->expects($this->once())
            ->method('determineFrom')
            ->with($this->streamName->name)
            ->willReturn(null);

        $this->eventStreamProvider->expects($this->once())
            ->method('createStream')
            ->with($this->streamName->name, $tableName, null)
            ->willReturn(true);

        $schemaBuilder = $this->createMock(SchemaBuilder::class);

        $this->connection->expects($this->once())
            ->method('getSchemaBuilder')
            ->willReturn($schemaBuilder);

        $schemaBuilder->expects($this->once())
            ->method('drop')
            ->with($tableName);

        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willReturn(true);

        $this->streamPersistence->expects($this->once())
            ->method('up')
            ->with($tableName)
            ->willThrowException(QueryExceptionStub::withCode('some_code'));

        $this->eventStore()->firstCommit(new Stream($this->streamName));
    }

    public function testAmendStream(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->exactly(2))
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->writeLock->expects($this->once())
            ->method('acquireLock')
            ->with($tableName)
            ->willReturn(true);

        $this->writeLock->expects($this->once())
            ->method('releaseLock')
            ->with($tableName)
            ->willReturn(true);

        $builder = $this->createMock(Builder::class);

        $builder->expects($this->once())
            ->method('useWritePdo')
            ->willReturn($builder);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $streamEvents = iterator_to_array($this->provideStreamEvents());

        $this->streamPersistence->expects($this->exactly(4))
            ->method('serialize')
            ->with($this->isInstanceOf(SomeEvent::class))
            ->willReturnMap(
                [
                    [$streamEvents[0], ['headers' => 'foo', 'content' => 'bar']],
                    [$streamEvents[1], ['headers' => 'foo', 'content' => 'bar']],
                    [$streamEvents[2], ['headers' => 'foo', 'content' => 'bar']],
                    [$streamEvents[3], ['headers' => 'foo', 'content' => 'bar']],
                ]
            )
            ->willReturn(['headers' => 'foo', 'content' => 'bar']);

        $expectedEvents = [
            ['headers' => 'foo', 'content' => 'bar'],
            ['headers' => 'foo', 'content' => 'bar'],
            ['headers' => 'foo', 'content' => 'bar'],
            ['headers' => 'foo', 'content' => 'bar'],
        ];

        $builder->expects($this->once())
            ->method('insert')
            ->with($expectedEvents)
            ->willReturn(true);

        $eventStore = $this->eventStore();

        $eventStore->amend(new Stream($this->streamName, $streamEvents));
    }

    public function testExceptionRaisedOnAmendWhenAcquireLockFailed(): void
    {
        $this->expectException(ConnectionConcurrencyException::class);
        $this->expectExceptionMessage('Failed to acquire lock');

        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->writeLock->expects($this->once())
            ->method('acquireLock')
            ->with($tableName)
            ->willReturn(false);

        $this->writeLock->expects($this->never())->method('releaseLock');

        $this->streamPersistence->expects($this->exactly(4))
            ->method('serialize')
            ->with($this->isInstanceOf(SomeEvent::class))
            ->willReturn([]);

        $eventStore = $this->eventStore();
        $streamEvents = iterator_to_array($this->provideStreamEvents());

        $eventStore->amend(new Stream($this->streamName, $streamEvents));
    }

    #[DataProvider('provideAnyException')]
    public function testAlwaysReleaseLockOnAmendWhenExceptionIsRaised(Throwable $exception): void
    {
        $this->expectException($exception::class);

        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->exactly(2))
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->writeLock->expects($this->once())
            ->method('acquireLock')
            ->with($tableName)
            ->willReturn(true);

        $this->writeLock->expects($this->once())
            ->method('releaseLock')
            ->with($tableName)
            ->willReturn(true);

        $builder = $this->createMock(Builder::class);

        $builder->expects($this->once())
            ->method('useWritePdo')
            ->willReturn($builder);

        $this->connection->expects($this->once())
            ->method('table')
            ->with($tableName)
            ->willReturn($builder);

        $streamEvents = iterator_to_array($this->provideStreamEvents());

        $this->streamPersistence->expects($this->exactly(4))
            ->method('serialize')
            ->with($this->isInstanceOf(SomeEvent::class))
            ->willReturnMap(
                [
                    [$streamEvents[0], ['headers' => 'foo', 'content' => 'bar']],
                    [$streamEvents[1], ['headers' => 'foo', 'content' => 'bar']],
                    [$streamEvents[2], ['headers' => 'foo', 'content' => 'bar']],
                    [$streamEvents[3], ['headers' => 'foo', 'content' => 'bar']],
                ]
            )
            ->willReturn(['headers' => 'foo', 'content' => 'bar']);

        $builder->expects($this->once())
            ->method('insert')
            ->with($this->isType('array'))
            ->willThrowException($exception);

        $eventStore = $this->eventStore();

        $eventStore->amend(new Stream($this->streamName, $streamEvents));
    }

    public function testReturnEarlyOnAmendWithEmptyStreamEvents(): void
    {
        $this->streamPersistence->expects($this->never())->method('tableName');

        $this->writeLock->expects($this->never())->method('acquireLock');
        $this->writeLock->expects($this->never())->method('releaseLock');

        $this->streamPersistence->expects($this->never())->method('serialize');

        $eventStore = $this->eventStore();

        $eventStore->amend(new Stream($this->streamName, []));
    }

    public function testDeleteStream(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willReturn(true);

        $schemaBuilder = $this->createMock(SchemaBuilder::class);

        $schemaBuilder->expects($this->once())->method('drop')->with($tableName);

        $this->connection->expects($this->once())
            ->method('getSchemaBuilder')
            ->willReturn($schemaBuilder);

        $eventStore = $this->eventStore();

        $eventStore->delete($this->streamName);
    }

    public function testExceptionRaisedOnDeleteWhenEventStreamProviderFailedToDelete(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willReturn(false);

        $this->connection->expects($this->never())->method('getSchemaBuilder');

        $eventStore = $this->eventStore(null);

        $eventStore->delete($this->streamName);
    }

    public function testQueryExceptionRaisedOnDeleteWhenEventStreamProviderFailedToDelete(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('1234');

        $queryException = QueryExceptionStub::withCode('1234');
        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willThrowException($queryException);

        $eventStore = $this->eventStore();

        $eventStore->delete($this->streamName);
    }

    public function testFailedSilentlyOnDeleteWhenNoAffectedRows(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $queryException = QueryExceptionStub::withCode('00000');

        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willThrowException($queryException);

        $eventStore = $this->eventStore();

        // hold previous exception and drop stream table
        $schemaBuilder = $this->createMock(SchemaBuilder::class);

        $schemaBuilder->expects($this->once())->method('drop')->with($tableName);

        $this->connection->expects($this->once())
            ->method('getSchemaBuilder')
            ->willReturn($schemaBuilder);

        $eventStore->delete($this->streamName);
    }

    public function testExceptionRaisedWhenDroppingTableFailed(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('1234');

        $tableName = 'read_customer';

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willReturn(true);

        $schemaBuilder = $this->createMock(SchemaBuilder::class);

        $this->connection->expects($this->once())
            ->method('getSchemaBuilder')
            ->willReturn($schemaBuilder);

        $queryException = QueryExceptionStub::withCode('1234');

        $schemaBuilder->expects($this->once())->method('drop')->with($tableName)->willThrowException($queryException);

        $eventStore = $this->eventStore();

        $eventStore->delete($this->streamName);
    }

    public function itFailSilentlyWhenDroppingTableFailed(): void
    {
        $tableName = 'read_customer';
        $schemaBuilder = $this->createMock(SchemaBuilder::class);

        $this->streamPersistence->expects($this->once())
            ->method('tableName')
            ->with($this->streamName)
            ->willReturn($tableName);

        $this->eventStreamProvider->expects($this->once())
            ->method('deleteStream')
            ->with($this->streamName->name)
            ->willReturn(true);

        $this->connection->expects($this->once())->method('getSchemaBuilder')->willReturn($schemaBuilder);

        $queryException = QueryExceptionStub::withCode('00000');

        $schemaBuilder->expects($this->once())->method('drop')->with($tableName)->willThrowException($queryException);

        $eventStore = $this->eventStore();

        $eventStore->delete($this->streamName);
    }

    public static function provideAnyException(): Generator
    {
        yield [new RuntimeException('some exception')];
        yield [new InvalidArgumentException('some exception')];
    }

    public static function provideWriteLock(): Generator
    {
        yield [new FakeWriteLock()];
        yield [null];
    }
}
