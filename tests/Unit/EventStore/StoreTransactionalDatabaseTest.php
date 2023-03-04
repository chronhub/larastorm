<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Generator;
use RuntimeException;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;

#[CoversClass(EventStoreTransactionalDatabase::class)]
final class StoreTransactionalDatabaseTest extends UnitTestCase
{
    private Connection|MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    #[Test]
    public function it_start_transaction(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');

        $this->eventStore()->beginTransaction();
    }

    #[Test]
    public function it_raise_exception_when_transaction_already_started(): void
    {
        $this->expectException(TransactionAlreadyStarted::class);

        $this->connection->expects($this->once())->method('beginTransaction')->willThrowException(new TransactionAlreadyStarted());

        $this->eventStore()->beginTransaction();
    }

    #[Test]
    public function it_commit_transaction(): void
    {
        $this->connection->expects($this->once())->method('commit');

        $this->eventStore()->commitTransaction();
    }

    #[Test]
    public function it_raise_exception_when_transaction_not_started_on_commit(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->connection->expects($this->once())->method('commit')->willThrowException(new TransactionNotStarted());

        $this->eventStore()->commitTransaction();
    }

    #[Test]
    public function it_rollback_transaction(): void
    {
        $this->connection->expects($this->once())->method('rollBack');

        $this->eventStore()->rollbackTransaction();
    }

    #[Test]
    public function it_raise_exception_when_transaction_not_started_on_rollback(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->connection->expects($this->once())->method('rollBack')->willThrowException(new TransactionNotStarted());

        $this->eventStore()->rollbackTransaction();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideValue')]
    #[Test]
    public function it_handle_process_fully_transactional(mixed $value): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $result = $this->eventStore()->transactional(fn (): mixed => $value);

        $this->assertEquals($value, $result);
    }

    #[Test]
    public function it_handle_process_fully_transactional_and_return_true_as_default_when_callback_result_is_null(): void
    {
        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');

        $result = $this->eventStore()->transactional(fn (): null => null);

        $this->assertEquals(true, $result);
    }

    #[Test]
    public function it_raise_exception_and_rollback_on_fully_transactional(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('foo');

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        $exception = new RuntimeException('foo');
        $this->eventStore()->transactional(fn () => throw $exception);
    }

    #[Test]
    public function it_assert_in_transaction(): void
    {
        $this->connection->expects($this->once())->method('transactionLevel')->willReturn(1);
        $this->assertTrue($this->eventStore()->inTransaction());
    }

    #[Test]
    public function it_assert_not_in_transaction(): void
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
