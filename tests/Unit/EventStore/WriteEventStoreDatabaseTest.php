<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Throwable;
use RuntimeException;
use Prophecy\Argument;
use InvalidArgumentException;
use Chronhub\Storm\Stream\Stream;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Illuminate\Database\Schema\Builder as SchemaBuilder;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;
use function iterator_to_array;

final class WriteEventStoreDatabaseTest extends ProphecyTestCase
{
    use ProvideTestingStore;

    /**
     * @test
     *
     * @dataProvider provideWriteLock
     */
    public function it_assert_write_query_builder(?WriteLockStrategy $writeLock = null): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->table($tableName)->willReturn($builder->reveal())->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore($writeLock)->getBuilderforWrite($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }

    /**
     * @test
     */
    public function it_assert_write_query_builder_with_mysql_write_lock(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);

        $this->connection->table($tableName)->willReturn($builder->reveal())->shouldBeCalledOnce();
        $builder->lockForUpdate()->willReturn($builder)->shouldBeCalledOnce();

        $queryBuilder = $this->eventStore(new MysqlWriteLock())->getBuilderforWrite($this->streamName);

        $this->assertSame($builder->reveal(), $queryBuilder);
    }

    /**
     * @test
     */
    public function it_create_first_stream(): void
    {
        // create event stream table
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledTimes(2);
        $this->streamCategory->__invoke($this->streamName->name)->willReturn(null)->shouldBeCalledOnce();

        $this->eventStreamProvider
            ->createStream(
                $this->streamName->name,
                $tableName,
                null
            )
            ->willReturn(true)->shouldBeCalledOnce();

        // stream persistence
        $this->streamPersistence->up($tableName)->willReturn(null)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->firstCommit(new Stream($this->streamName));
    }

    /**
     * @test
     */
    public function it_create_first_stream_with_category_detected(): void
    {
        // create event stream table
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledTimes(2);
        $this->streamCategory->__invoke($this->streamName->name)->willReturn('admin')->shouldBeCalledOnce();

        $this->eventStreamProvider
            ->createStream(
                $this->streamName->name,
                $tableName,
                'admin'
            )
            ->willReturn(true)->shouldBeCalledOnce();

        // stream persistence
        $this->streamPersistence->up($tableName)->willReturn(null)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->firstCommit(new Stream($this->streamName));
    }

    /**
     * @test
     */
    public function it_raise_exception_on_create_first_stream_when_event_stream_provider_failed_to_create(): void
    {
        $this->expectException(ConnectionQueryFailure::class);
        $this->expectExceptionMessage("Unable to insert data for stream {$this->streamName->name} in event stream table");

        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->streamCategory->__invoke($this->streamName->name)->willReturn(null)->shouldBeCalledOnce();

        $this->eventStreamProvider
            ->createStream(
                $this->streamName->name,
                $tableName,
                null
            )
            ->willReturn(false)->shouldBeCalledOnce();

        // stream persistence
        $this->streamPersistence->up($tableName)->shouldNotBeCalled();

        $this->eventStore(null)->firstCommit(new Stream($this->streamName));
    }

    /**
     * @test
     */
    public function it_raise_exception_on_create_first_stream_when_up_stream_failed_to_create(): void
    {
        $this->expectException(QueryException::class);

        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledTimes(2);
        $this->streamCategory->__invoke($this->streamName->name)->willReturn(null)->shouldBeCalledOnce();

        $this->eventStreamProvider
            ->createStream(
                $this->streamName->name,
                $tableName,
                null
            )
            ->willReturn(true)->shouldBeCalledOnce();

        // stream persistence
        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalledOnce();
        $schemaBuilder->drop($tableName)->shouldBeCalledOnce();
        $this->eventStreamProvider->deleteStream($this->streamName->name)->willReturn(true)->shouldBeCalledOnce();
        $this->streamPersistence->up($tableName)->willThrow(QueryExceptionStub::withCode('some_code'))->shouldBeCalledOnce();

        $this->eventStore(null)->firstCommit(new Stream($this->streamName));
    }

    /**
     * @test
     */
    public function it_amend_stream(): void
    {
        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledTimes(2);

        $this->writeLock->acquireLock($tableName)->willReturn(true)->shouldBeCalledOnce();
        $this->writeLock->releaseLock($tableName)->willReturn(true)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $streamEvents = iterator_to_array($this->provideStreamEvents());

        $this->streamPersistence
            ->serialize(Argument::type(SomeEvent::class))
            ->willReturn(['headers' => 'foo', 'content' => 'bar'])
            ->shouldBeCalledTimes(4);

        $expectedEvents = [
            ['headers' => 'foo', 'content' => 'bar'],
            ['headers' => 'foo', 'content' => 'bar'],
            ['headers' => 'foo', 'content' => 'bar'],
            ['headers' => 'foo', 'content' => 'bar'],
        ];

        $builder->insert($expectedEvents)->willReturn(Argument::type('bool'))->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->amend(new Stream($this->streamName, $streamEvents));
    }

    /**
     * @test
     */
    public function it_raise_exception_on_amend_stream_when_lock_failed_to_be_acquired(): void
    {
        $this->expectException(ConnectionConcurrencyException::class);
        $this->expectExceptionMessage('Failed to acquire lock');

        $tableName = 'read_customer';

        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $this->writeLock->acquireLock($tableName)->willReturn(false)->shouldBeCalledOnce();
        $this->writeLock->releaseLock($tableName)->shouldNotBeCalled();

        $this->streamPersistence
            ->serialize(Argument::type(SomeEvent::class))
            ->willReturn([])
            ->shouldBeCalledTimes(4);

        $eventStore = $this->eventStore(null);
        $streamEvents = iterator_to_array($this->provideStreamEvents());

        $eventStore->amend(new Stream($this->streamName, $streamEvents));
    }

    /**
     * @test
     *
     * @dataProvider provideAnyException
     */
    public function it_always_release_lock_when_exception_raised_on_amend_stream(Throwable $exception): void
    {
        $this->expectException($exception::class);

        $tableName = 'read_customer';
        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledTimes(2);

        $this->writeLock->acquireLock($tableName)->willReturn(true)->shouldBeCalledOnce();
        $this->writeLock->releaseLock($tableName)->willReturn(true)->shouldBeCalledOnce();

        $builder = $this->prophesize(Builder::class);
        $this->connection->table($tableName)->willReturn($builder)->shouldBeCalledOnce();

        $streamEvents = iterator_to_array($this->provideStreamEvents());

        $this->streamPersistence
            ->serialize(Argument::type(SomeEvent::class))
            ->willReturn(['headers' => 'foo', 'content' => 'bar'])
            ->shouldBeCalledTimes(4);

        $builder->insert(Argument::type('array'))->willThrow($exception)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->amend(new Stream($this->streamName, $streamEvents));
    }

    /**
     * @test
     */
    public function it_return_early_on_amend_streams_whens_stream_events_are_empty(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->tableName($this->streamName)->shouldNotBeCalled();

        $this->writeLock->acquireLock($tableName)->shouldNotBeCalled();
        $this->writeLock->releaseLock($tableName)->shouldNotBeCalled();

        $this->streamPersistence->serialize(Argument::type('object'))->shouldNotBeCalled();

        $eventStore = $this->eventStore(null);

        $eventStore->amend(new Stream($this->streamName, []));
    }

    /**
     * @test
     */
    public function it_delete_stream(): void
    {
        $tableName = 'read_customer';

        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();
        $this->eventStreamProvider->deleteStream($this->streamName->name)->willReturn(true)->shouldBeCalledOnce();

        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->drop($tableName)->shouldBeCalledOnce();
        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->delete($this->streamName);
    }

    /**
     * @test
     */
    public function it_raise_exception_on_delete_stream_when_event_stream_provider_failed_to_delete(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->eventStreamProvider->deleteStream($this->streamName->name)->willReturn(false)->shouldBeCalledOnce();

        $this->connection->getSchemaBuilder()->shouldNotBeCalled();

        $eventStore = $this->eventStore(null);

        $eventStore->delete($this->streamName);
    }

    /**
     * @test
     */
    public function it_raise_exception_on_delete_stream_when_query_exception_is_raised_from_event_stream_provider(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('1234');

        $queryException = QueryExceptionStub::withCode('1234');
        $this->eventStreamProvider->deleteStream($this->streamName->name)->willThrow($queryException)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->delete($this->streamName);
    }

    /**
     * @test
     */
    public function it_does_not_raise_exception_on_delete_stream_when_query_exception_is_raised_from_event_stream_provider_and_does_not_affected_rows(): void
    {
        $this->markAsRisky();

        $tableName = 'read_customer';

        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $queryException = QueryExceptionStub::withCode('00000');
        $this->eventStreamProvider->deleteStream($this->streamName->name)->willThrow($queryException)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        // hold previous exception and drop stream table
        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $schemaBuilder->drop($tableName)->shouldBeCalledOnce();
        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalledOnce();

        $eventStore->delete($this->streamName);
    }

    /**
     * @test
     */
    public function it_raise_exception_on_delete_stream_when_dropping_table_failed(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode('1234');

        $tableName = 'read_customer';

        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $this->eventStreamProvider->deleteStream($this->streamName->name)->willReturn(true)->shouldBeCalledOnce();

        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalledOnce();

        $queryException = QueryExceptionStub::withCode('1234');

        $schemaBuilder->drop($tableName)->willThrow($queryException)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->delete($this->streamName);
    }

    /**
     * @test
     */
    public function it_does_not_raise_exception_on_delete_stream_when_dropping_table_failed_and_does_not_affected_rows(): void
    {
        $this->markAsRisky();

        $tableName = 'read_customer';

        $this->streamPersistence->tableName($this->streamName)->willReturn($tableName)->shouldBeCalledOnce();

        $this->eventStreamProvider->deleteStream($this->streamName->name)->willReturn(true)->shouldBeCalledOnce();

        $schemaBuilder = $this->prophesize(SchemaBuilder::class);
        $this->connection->getSchemaBuilder()->willReturn($schemaBuilder)->shouldBeCalledOnce();

        $queryException = QueryExceptionStub::withCode('00000');

        $schemaBuilder->drop($tableName)->willThrow($queryException)->shouldBeCalledOnce();

        $eventStore = $this->eventStore(null);

        $eventStore->delete($this->streamName);
    }

    public function provideAnyException(): Generator
    {
        yield [new RuntimeException('some exception')];
        yield [new InvalidArgumentException('some exception')];
    }

    public function provideWriteLock(): Generator
    {
        yield [new FakeWriteLock()];
        yield [null];
    }
}
