<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Generator;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Projection\ConnectionQueryScope;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;

#[CoversClass(ConnectionQueryScope::class)]
final class ConnectionProjectionQueryScopeTest extends UnitTestCase
{
    private MockObject|Builder $builder;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = $this->createMock(Builder::class);
    }

    #[DataProvider('provideInvalidPosition')]
    #[Test]
    public function it_raise_exception_when_current_position_is_less_or_equals_than_zero(int $invalidPosition): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be greater than 0, current is '.$invalidPosition);

        $scope = new ConnectionQueryScope();

        $query = $scope->fromIncludedPosition();
        $query->setCurrentPosition($invalidPosition);

        $query->apply()($this->builder);
    }

    #[Test]
    public function it_filter_query(): void
    {
        $this->builder->expects($this->once())->method('where')->with('no', '>=', 20)->willReturn($this->builder);
        $this->builder->expects($this->once())->method('orderBy')->with('no')->willReturn($this->builder);

        $scope = new ConnectionQueryScope();

        $query = $scope->fromIncludedPosition();
        $query->setCurrentPosition(20);

        $query->apply()($this->builder);
    }

    #[DataProvider('provideInvalidPosition')]
    #[Test]
    public function it_raise_exception_when_current_position_is_less_or_equals_than_zero_with_limit(int $invalidPosition): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be greater than 0, current is '.$invalidPosition);

        $scope = new ConnectionQueryScope();

        $query = $scope->fromIncludedPositionWithLimit();
        $query->setCurrentPosition($invalidPosition);

        $query->apply()($this->builder);
    }

    #[Test]
    public function it_filter_query_with_limit(): void
    {
        $this->builder->expects($this->once())->method('where')->with('no', '>=', 20)->willReturn($this->builder);
        $this->builder->expects($this->once())->method('orderBy')->with('no')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('limit')->with(100)->willReturn($this->builder);

        $scope = new ConnectionQueryScope();

        $query = $scope->fromIncludedPositionWithLimit(100);
        $query->setCurrentPosition(20);

        $query->apply()($this->builder);
    }

    public static function provideInvalidPosition(): Generator
    {
        yield [0];
        yield [-5];
    }
}
