<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Exceptions;

use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;

#[CoversClass(ConnectionConcurrencyException::class)]
final class ConnectionConcurrencyExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_assert_message_from_acquire_lock_failed(): void
    {
        $exception = ConnectionConcurrencyException::failedToAcquireLock();

        $this->assertEquals('Failed to acquire lock', $exception->getMessage());
    }

    #[Test]
    public function it_assert_message_from_unlock_stream_failed(): void
    {
        $queryException = QueryExceptionStub::withCode('1234');

        $exception = ConnectionConcurrencyException::fromUnlockStreamFailure($queryException);

        $this->assertEquals("Events or Aggregates ids have already been used in the same stream\n", $exception->getMessage());
    }

    #[Test]
    public function it_assert_message_from_unlock_stream_failed_with_pdo_exception(): void
    {
        $pdoException = new PDOException('foo', 0);
        $pdoException->errorInfo = ['some error', 'not printed', 'some_info'];

        $queryException = new QueryException('', '', [], $pdoException);

        $exception = ConnectionConcurrencyException::fromUnlockStreamFailure($queryException);

        $this->assertEquals(
            "Events or Aggregates ids have already been used in the same stream\nError some error\n Error-Info some_info",
            $exception->getMessage()
        );
    }
}
