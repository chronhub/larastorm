<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Projection\Query\FromIncludedPosition;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Projector\Exceptions\InvalidArgumentException;
use Generator;
use Illuminate\Database\Query\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(FromIncludedPosition::class)]
final class FromIncludedPositionTest extends UnitTestCase
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

        $queryFilter = new FromIncludedPosition();
        $queryFilter->setCurrentPosition($invalidPosition);

        $queryFilter->apply()($this->builder);
    }

    public function testFilterQuery(): void
    {
        $this->builder->expects($this->once())->method('where')->with('no', '>=', 20)->willReturn($this->builder);
        $this->builder->expects($this->once())->method('orderBy')->with('no')->willReturn($this->builder);

        $queryFilter = new FromIncludedPosition();
        $queryFilter->setCurrentPosition(20);

        $queryFilter->apply()($this->builder);
    }

    public static function provideInvalidPosition(): Generator
    {
        yield [0];
        yield [-5];
    }
}
