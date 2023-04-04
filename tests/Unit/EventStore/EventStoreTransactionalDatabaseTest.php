<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Generator;
use RuntimeException;
use Illuminate\Database\Connection;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;

#[CoversClass(EventStoreTransactionalDatabase::class)]
final class EventStoreTransactionalDatabaseTest extends UnitTestCase
{
    private Connection|MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testStartTransaction(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');

        $this->eventStore()->beginTransaction();
    }

    public function testExceptionRaisedWhenTransactionAlreadyStarted(): void
    {
        $this->expectException(TransactionAlreadyStarted::class);

        $this->connection->expects($this->once())->method('beginTransaction')->willThrowException(new TransactionAlreadyStarted());

        $this->eventStore()->beginTransaction();
    }

    public function testCommitTransaction(): void
    {
        $this->connection->expects($this->once())->method('commit');

        $this->eventStore()->commitTransaction();
    }

    public function testExceptionRaisedWhenTransactionNotStartedOnCommit(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->connection->expects($this->once())->method('commit')->willThrowException(new TransactionNotStarted());

        $this->eventStore()->commitTransaction();
    }

    public function testRollbackTransaction(): void
    {
        $this->connection->expects($this->once())->method('rollBack');

        $this->eventStore()->rollbackTransaction();
    }

    public function testExceptionRaisedWhenTransactionNotStartedOnRollback(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->connection->expects($this->once())->method('rollBack')->willThrowException(new TransactionNotStarted());

        $this->eventStore()->rollbackTransaction();
    }

    #[DataProvider('provideValue')]
    public function testFullTransactional(mixed $value): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $result = $this->eventStore()->transactional(fn (): mixed => $value);

        $this->assertEquals($value, $result);
    }

    public function testFullTransactionalAndReturnTrueWithNullResult(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $result = $this->eventStore()->transactional(fn (): null => null);

        $this->assertEquals(true, $result);
    }

    public function testRollbackOnFullTransactionalWhenExceptionRaised(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('foo');

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $exception = new RuntimeException('foo');
        $this->eventStore()->transactional(fn () => throw $exception);
    }

    public function testCheckInTransaction(): void
    {
        $this->connection->expects($this->once())->method('transactionLevel')->willReturn(1);
        $this->assertTrue($this->eventStore()->inTransaction());
    }

    public function testCheckNotInTransaction(): void
    {
        $this->connection->expects($this->any())->method('transactionLevel')->willReturn(0);
        $this->assertFalse($this->eventStore()->inTransaction());
    }

    public static function provideValue(): Generator
    {
        yield ['foo'];
        yield [42];
        yield [3.14];
        yield [new stdClass()];
        yield [true];
        yield [false];
    }

    private function eventStore(): EventStoreTransactionalDatabase
    {
        return new EventStoreTransactionalDatabase(
            $this->connection,
            $this->createMock(StreamPersistence::class),
            $this->createMock(StreamEventLoaderConnection::class),
            $this->createMock(EventStreamProvider::class),
            $this->createMock(StreamCategory::class),
            $this->createMock(WriteLockStrategy::class),
        );
    }
}
