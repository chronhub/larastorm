<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs;

use Chronhub\Larastorm\Support\ReadModel\AbstractQueryModelConnection;
use Closure;

final class QueryModelConnectionStub extends AbstractQueryModelConnection
{
    public function call(Closure $closure): mixed
    {
        $context = Closure::bind($closure, $this, self::class);

        return $context($this);
    }

    protected function up(): callable
    {
        return fn () => true;
    }

    protected function tableName(): string
    {
        return 'read_foo';
    }
}
