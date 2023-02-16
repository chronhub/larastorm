<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Generator;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\Query\Builder;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\Projection\ConnectionProjectionQueryScope;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;

final class ConnectionProjectionQueryScopeTest extends ProphecyTestCase
{
    private Builder|ObjectProphecy $builder;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = $this->prophesize(Builder::class);
    }

    /**
     * @test
     *
     * @dataProvider provideInvalidPosition
     */
    public function it_raise_exception_when_current_position_is_less_or_equals_than_zero(int $invalidPosition): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be greater than 0, current is '.$invalidPosition);

        $scope = new ConnectionProjectionQueryScope();

        $query = $scope->fromIncludedPosition();
        $query->setCurrentPosition($invalidPosition);

        $query->apply()($this->builder->reveal());
    }

    /**
     * @test
     */
    public function it_filter_query(): void
    {
        $this->builder->where('no', '>=', 20)->willReturn($this->builder)->shouldBeCalledOnce();
        $this->builder->orderBy('no')->willReturn($this->builder)->shouldBeCalledOnce();

        $scope = new ConnectionProjectionQueryScope();

        $query = $scope->fromIncludedPosition();
        $query->setCurrentPosition(20);

        $query->apply()($this->builder->reveal());
    }

    public function provideInvalidPosition(): Generator
    {
        yield [0];
        yield [-5];
    }
}
