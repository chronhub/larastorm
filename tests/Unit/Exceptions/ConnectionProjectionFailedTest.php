<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Exceptions;

use PDOException;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;

#[CoversClass(ConnectionProjectionFailed::class)]
final class ConnectionProjectionFailedTest extends UnitTestCase
{
    #[Test]
    public function it_assert_from_query_exception(): void
    {
        $queryException = QueryExceptionStub::withCode('123');

        $exception = ConnectionProjectionFailed::fromQueryException($queryException);

        $this->assertSame($queryException, $exception->getPrevious());
        $this->assertEquals('A query exception occurred', $exception->getMessage());
        $this->assertEquals($queryException->getCode(), $exception->getCode());
    }

    #[Test]
    public function it_assert_from_query_exception_with_previous_pdo_exception(): void
    {
        $pdoException = new PDOException('foo', 0);
        $pdoException->errorInfo = ['some error', 'not printed', 'some_info'];

        $queryException = new QueryException('', '', [], $pdoException);

        $exception = ConnectionProjectionFailed::fromQueryException($queryException);

        $this->assertEquals(
            "Error some error. \nError-Info: some_info",
            $exception->getMessage()
        );
    }
}
