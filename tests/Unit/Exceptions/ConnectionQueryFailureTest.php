<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Exceptions;

use PDOException;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;

final class ConnectionQueryFailureTest extends UnitTestCase
{
    /**
     * @test
     */
    public function it_assert_from_query_exception(): void
    {
        $queryException = QueryExceptionStub::withCode('123');

        $exception = ConnectionQueryFailure::fromQueryException($queryException);

        $this->assertSame($queryException, $exception->getPrevious());
        $this->assertEquals('A query exception occurred', $exception->getMessage());
        $this->assertEquals($queryException->getCode(), $exception->getCode());
    }

    /**
     * @test
     */
    public function it_assert_from_query_exception_with_previous_pdo_exception(): void
    {
        $pdoException = new PDOException('foo', 0);
        $pdoException->errorInfo = ['some error', 'not printed', 'some_info'];

        $queryException = new QueryException('', '', [], $pdoException);

        $exception = ConnectionQueryFailure::fromQueryException($queryException);

        $this->assertEquals(
            "Error some error. \nError-Info: some_info",
            $exception->getMessage()
        );
    }
}
