<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeException;

final class QueryExceptionStub extends QueryException
{
    public static function withCode(string $code): self
    {
        $previousException = new SomeException('A query exception occurred');
        $previousException->setCodeAsString($code);

        return new self('some_connection_name', 'some_sql', [], $previousException);
    }
}
