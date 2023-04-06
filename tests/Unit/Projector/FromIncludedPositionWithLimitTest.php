<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Projection\Query\FromIncludedPositionWithLimit;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Generator;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(FromIncludedPositionWithLimit::class)]
final class FromIncludedPositionWithLimitTest extends UnitTestCase
{
    private Builder|MockObject $builder;

    public function setUp(): void
    {
        parent::setUp();

        $this->builder = $this->createMock(Builder::class);
    }

    #[DataProvider('provideInvalidPosition')]
    public function testExceptionRaisedWhenCurrentStreamPositionIsLessThanOne(int $invalidPosition): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Position must be greater than 0, current is '.$invalidPosition);

        $queryFilter = new FromIncludedPositionWithLimit(1000);
        $queryFilter->setCurrentPosition($invalidPosition);

        $queryFilter->apply()($this->builder);
    }

    #[DataProvider('provideQueryLimit')]
    public function testFilterQuery(int $limit): void
    {
        $this->builder->expects($this->once())->method('where')->with('no', '>=', 20)->willReturn($this->builder);
        $this->builder->expects($this->once())->method('orderBy')->with('no')->willReturn($this->builder);
        $this->builder->expects($this->once())->method('limit')->with($limit)->willReturn($this->builder);

        $queryFilter = new FromIncludedPositionWithLimit($limit);
        $queryFilter->setCurrentPosition(20);

        $queryFilter->apply()($this->builder);
    }

    public static function provideInvalidPosition(): Generator
    {
        yield [0];
        yield [-5];
    }

    public static function provideQueryLimit(): Generator
    {
        yield [100];
        yield [1000];
        yield [5000];
    }
}
