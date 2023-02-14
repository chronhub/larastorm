<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Exception;
use Generator;
use RuntimeException;
use InvalidArgumentException;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\PgsqlTransactionalEventStore;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;

final class PgsqlTransactionalEventStoreTest extends ProphecyTestCase
{
    private TransactionalChronicler|ObjectProphecy $chronicler;

    protected function setUp(): void
    {
        $this->chronicler = $this->prophesize(TransactionalChronicler::class);
    }

    /**
     * @test
     */
    public function it_start_transaction(): void
    {
        $this->chronicler->beginTransaction()->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->beginTransaction();
    }

    /**
     * @test
     *
     * @dataProvider provideException
     */
    public function it_does_not_hold_exception_on_begin(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->beginTransaction()->willThrow($exception)->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->beginTransaction();
    }

    /**
     * @test
     */
    public function it_commit_transaction(): void
    {
        $this->chronicler->commitTransaction()->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->commitTransaction();
    }

    /**
     * @test
     *
     * @dataProvider provideException
     */
    public function it_does_not_hold_exception_on_commit(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->commitTransaction()->willThrow($exception)->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->commitTransaction();
    }

    /**
     * @test
     */
    public function it_rollback_transaction(): void
    {
        $this->chronicler->rollbackTransaction()->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->rollbackTransaction();
    }

    /**
     * @test
     *
     * @dataProvider provideException
     */
    public function it_does_not_hold_exception_on_rollback(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->rollbackTransaction()->willThrow($exception)->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->rollbackTransaction();
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_assert_in_transaction(bool $inTransaction): void
    {
        $this->chronicler->inTransaction()->willReturn($inTransaction)->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $this->assertEquals($inTransaction, $decorator->inTransaction());
    }

    /**
     * @test
     *
     * @dataProvider provideValue
     */
    public function it_process_fully_transactional(mixed $value): void
    {
        $callback = fn (): mixed => $value;

        /** @phpstan-ignore-next-line */
        $this->chronicler->transactional($callback)->willReturn($value)->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $this->assertEquals($value, $decorator->transactional($callback));
    }

    /**
     * @test
     *
     * @dataProvider provideException
     */
    public function it_does_not_hold_exception_on_fully_transactional(Exception $exception): void
    {
        $this->expectException($exception::class);
        $this->expectExceptionMessage('foo');

        $callback = fn (): bool => true;

        /** @phpstan-ignore-next-line */
        $this->chronicler->transactional($callback)->willThrow($exception)->shouldBeCalledOnce();

        $decorator = new PgsqlTransactionalEventStore($this->chronicler->reveal());

        $decorator->transactional($callback);
    }

    public function provideException(): Generator
    {
        yield [new RuntimeException('foo')];
        yield [new InvalidArgumentException('foo')];
        yield [new TransactionNotStarted('foo')];
        yield [new TransactionAlreadyStarted('foo')];
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }

    public function provideValue(): Generator
    {
        yield [false];
        yield [true];
        yield ['foo'];
        yield [42];
        yield [3.14];
        yield [new stdClass()];
    }
}
