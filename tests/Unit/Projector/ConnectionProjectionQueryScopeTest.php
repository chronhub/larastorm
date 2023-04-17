<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Projection\ConnectionQueryScope;
use Chronhub\Larastorm\Projection\Query\FromIncludedPosition;
use Chronhub\Larastorm\Projection\Query\FromIncludedPositionWithLimit;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ConnectionQueryScope::class)]
final class ConnectionProjectionQueryScopeTest extends UnitTestCase
{
    public function testInstance(): void
    {
        $scope = new ConnectionQueryScope();

        $this->assertSame(FromIncludedPosition::class, $scope->fromIncludedPosition()::class);

    }

    #[DataProvider('provideLimit')]
    public function testWithLimit(int $limit): void
    {
        $scope = new ConnectionQueryScope();

        $queryFilter = $scope->fromIncludedPositionWithLimit($limit);

        $this->assertInstanceOf(FromIncludedPositionWithLimit::class, $queryFilter);
        $this->assertSame($limit, $queryFilter->limit);
    }

    public static function provideLimit(): Generator
    {
        yield [500];
        yield [1000];
        yield [10000];
    }
}
