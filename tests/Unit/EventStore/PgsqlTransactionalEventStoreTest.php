<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\PgsqlTransactionalEventStore;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Exception;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use stdClass;

#[CoversClass(PgsqlTransactionalEventStore::class)]
final class PgsqlTransactionalEventStoreTest extends UnitTestCase
{
    private TransactionalChronicler|MockObject $chronicler;

    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(TransactionalChronicler::class);
    }

    public function testStartTransaction(): void
    {
        $this->chronicler->expects($this->once())->method('beginTransaction');

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->beginTransaction();
    }

    #[DataProvider('provideException')]
    public function testDoesNotHoldExceptionOnBeginTransaction(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->expects($this->once())->method('beginTransaction')->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->beginTransaction();
    }

    public function testCommitTransaction(): void
    {
        $this->chronicler->expects($this->once())->method('commitTransaction');

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->commitTransaction();
    }

    #[DataProvider('provideException')]
    public function testDoesNotHoldExceptionOnCommitTransaction(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->expects($this->once())->method('commitTransaction')->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->commitTransaction();
    }

    public function testRollbackTransaction(): void
    {
        $this->chronicler->expects($this->once())->method('rollbackTransaction');

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->rollbackTransaction();
    }

    #[DataProvider('provideException')]
    public function testDoesNotHoldExceptionOnRollbackTransaction(Exception $exception): void
    {
        $this->expectException($exception::class);

        $this->chronicler->expects($this->once())->method('rollbackTransaction')->willThrowException($exception);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $decorator->rollbackTransaction();
    }

    #[DataProvider('provideBoolean')]
    public function testCheckInTransaction(bool $inTransaction): void
    {
        $this->chronicler->expects($this->once())->method('inTransaction')->willReturn($inTransaction);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $this->assertEquals($inTransaction, $decorator->inTransaction());
    }

    #[DataProvider('provideValue')]
    public function testFullTransactional(mixed $value): void
    {
        $callback = fn (): mixed => $value;

        $this->chronicler->expects($this->once())->method('transactional')->with($callback)->willReturn($value);

        $decorator = new PgsqlTransactionalEventStore($this->chronicler);

        $this->assertEquals($value, $decorator->transactional($callback));
    }

    #[DataProvider('provideException')]
    public function testDoesNotHoldExceptionOnFullTransactional(Exception $exception): void
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
