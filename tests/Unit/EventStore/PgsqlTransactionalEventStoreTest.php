<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Exception;
use Generator;
use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\PgsqlTransactionalEventStore;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;

#[CoversClass(PgsqlTransactionalEventStore::class)]
final class PgsqlTransactionalEventStoreTest extends UnitTestCase
{
    private TransactionalChronicler|MockObject $chronicler;

    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(TransactionalChronicler::class);
    }

    #[Test]
    public function it_start_transaction(): void
    {
        $this->chronicler->expects($this->once())->method('beginTransaction');

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->beginTransaction();
    }

    #[DataProvider('provideException')]
    #[Test]
    public function it_does_not_hold_exception_on_begin(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->expects($this->once())->method('beginTransaction')->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->beginTransaction();
    }

    #[Test]
    public function it_commit_transaction(): void
    {
        $this->chronicler->expects($this->once())->method('commitTransaction');

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->commitTransaction();
    }

    #[DataProvider('provideException')]
    #[Test]
    public function it_does_not_hold_exception_on_commit(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->expects($this->once())->method('commitTransaction')->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->commitTransaction();
    }

    #[Test]
    public function it_rollback_transaction(): void
    {
        $this->chronicler->expects($this->once())->method('rollbackTransaction');

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->rollbackTransaction();
    }

    #[DataProvider('provideException')]
    #[Test]
    public function it_does_not_hold_exception_on_rollback(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->expects($this->once())->method('rollbackTransaction')->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->rollbackTransaction();
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_assert_in_transaction(bool $inTransaction): void
    {
        $this->chronicler->expects($this->once())->method('inTransaction')->willReturn($inTransaction);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $this->assertEquals($inTransaction, $decorator->inTransaction());
    }

    #[DataProvider('provideValue')]
    #[Test]
    public function it_process_fully_transactional(mixed $value): void
    {
        $callback = fn (): mixed => $value;

        $this->chronicler->expects($this->once())->method('transactional')->with($callback)->willReturn($value);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $this->assertEquals($value, $decorator->transactional($callback));
    }

    #[DataProvider('provideException')]
    #[Test]
    public function it_does_not_hold_exception_on_fully_transactional(Exception $exception): void
    {
        $this->expectException($exception::class);
        $this->expectExceptionMessage('foo');

        $callback = fn (): bool => true;

        $this->chronicler->expects($this->once())->method('transactional')->with($callback)->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->transactional($callback);
    }

    public static function provideException(): Generator
    {
        yield [new RuntimeException('foo')];
        yield [new InvalidArgumentException('foo')];
        yield [new TransactionNotStarted('foo')];
        yield [new TransactionAlreadyStarted('foo')];
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }

    public static function provideValue(): Generator
    {
        yield [false];
        yield [true];
        yield ['foo'];
        yield [42];
        yield [3.14];
        yield [new stdClass()];
    }
}
