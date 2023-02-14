<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Generator;
use RuntimeException;
use Illuminate\Database\Connection;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\StoreTransactionalDatabase;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoaderConnection;

final class StoreTransactionalDatabaseTest extends ProphecyTestCase
{
    private Connection|ConnectionInterface|ObjectProphecy $connection;

    protected function setUp(): void
    {
        $this->connection = $this->prophesize(Connection::class);
    }

    /**
     * @test
     */
    public function it_start_transaction(): void
    {
        $this->connection->beginTransaction()->shouldBeCalledOnce();

        $this->eventStore()->beginTransaction();
    }

    /**
     * @test
     */
    public function it_raise_exception_when_transaction_already_started(): void
    {
        $this->expectException(TransactionAlreadyStarted::class);

        $this->connection->beginTransaction()->willThrow(new TransactionAlreadyStarted())->shouldBeCalledOnce();

        $this->eventStore()->beginTransaction();
    }

    /**
     * @test
     */
    public function it_commit_transaction(): void
    {
        $this->connection->commit()->shouldBeCalledOnce();

        $this->eventStore()->commitTransaction();
    }

    /**
     * @test
     */
    public function it_raise_exception_when_transaction_not_started_on_commit(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->connection->commit()->willThrow(new TransactionNotStarted())->shouldBeCalledOnce();

        $this->eventStore()->commitTransaction();
    }

    /**
     * @test
     */
    public function it_rollback_transaction(): void
    {
        $this->connection->rollBack()->shouldBeCalledOnce();

        $this->eventStore()->rollbackTransaction();
    }

    /**
     * @test
     */
    public function it_raise_exception_when_transaction_not_started_on_rollback(): void
    {
        $this->expectException(TransactionNotStarted::class);

        $this->connection->rollBack()->willThrow(new TransactionNotStarted())->shouldBeCalledOnce();

        $this->eventStore()->rollbackTransaction();
    }

    /**
     * @test
     *
     * @dataProvider provideValue
     */
    public function it_handle_process_fully_transactional(mixed $value): void
    {
        $this->connection->beginTransaction()->shouldBeCalledOnce();
        $this->connection->commit()->shouldBeCalledOnce();

        $result = $this->eventStore()->transactional(fn (): mixed => $value);

        $this->assertEquals($value, $result);
    }

    /**
     * @test
     */
    public function it_handle_process_fully_transactional_and_return_true_as_default_when_callback_result_is_null(): void
    {
        $this->connection->beginTransaction()->shouldBeCalledOnce();
        $this->connection->commit()->shouldBeCalledOnce();

        $result = $this->eventStore()->transactional(fn (): null => null);

        $this->assertEquals(true, $result);
    }

    /**
     * @test
     */
    public function it_raise_exception_and_rollback_on_fully_transactional(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('foo');

        $this->connection->beginTransaction()->shouldBeCalledOnce();
        $this->connection->rollBack()->shouldBeCalledOnce();
        $this->connection->commit()->shouldNotBeCalled();

        $exception = new RuntimeException('foo');
        $this->eventStore()->transactional(fn () => throw $exception);
    }

    /**
     * @test
     */
    public function it_assert_in_transaction(): void
    {
        $this->connection->transactionLevel()->willReturn(1)->shouldBeCalled();
        $this->assertTrue($this->eventStore()->inTransaction());

        $this->connection->transactionLevel()->willReturn(0)->shouldBeCalled();
        $this->assertFalse($this->eventStore()->inTransaction());
    }

    public function provideValue(): Generator
    {
        yield ['foo'];
        yield [42];
        yield [3.14];
        yield [new stdClass()];
        yield [true];
        yield [false];
    }

    private function eventStore(): StoreTransactionalDatabase
    {
        return new StoreTransactionalDatabase(
            $this->connection->reveal(),
            $this->prophesize(StreamPersistence::class)->reveal(),
            $this->prophesize(StreamEventLoaderConnection::class)->reveal(),
            $this->prophesize(EventStreamProvider::class)->reveal(),
            $this->prophesize(StreamCategory::class)->reveal(),
            $this->prophesize(WriteLockStrategy::class)->reveal(),
        );
    }
}
