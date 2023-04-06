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
use function get_class;

#[CoversClass(ConnectionQueryScope::class)]
final class ConnectionProjectionQueryScopeTest extends UnitTestCase
{
    public function testFromIncludedPositionInstance(): void
    {
        $scope = new ConnectionQueryScope();

        $this->assertSame(FromIncludedPosition::class, get_class($scope->fromIncludedPosition()));

    }

    #[DataProvider('provideLimit')]
    public function testFromIncludedPositionInstanceWithLimit(int $limit): void
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
