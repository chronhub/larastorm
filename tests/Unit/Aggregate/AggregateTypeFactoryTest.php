<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Aggregate;

use Chronhub\Larastorm\Aggregate\AggregateTypeFactory;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootChildStub;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootFinalStub;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Aggregate\AggregateType;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AggregateTypeFactory::class)]
final class AggregateTypeFactoryTest extends UnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
    }

    public function testDetermineAggregateRootFromRootClassName(): void
    {
        $factory = new AggregateTypeFactory(fn () => $this->container);

        $aggregateType = $factory->createType(AggregateRootStub::class);

        $this->assertInstanceOf(AggregateType::class, $aggregateType);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
    }

    public function testDetermineAggregateRootFromRootClassNameAndLineage(): void
    {
        $factory = new AggregateTypeFactory(fn () => $this->container);

        $aggregateType = $factory->createType([
            'root' => AggregateRootStub::class,
            'lineage' => [
                AggregateRootChildStub::class,
            ],
        ]);

        $this->assertInstanceOf(AggregateType::class, $aggregateType);

        $this->assertTrue($aggregateType->isSupported(AggregateRootChildStub::class));
        $this->assertFalse($aggregateType->isSupported(AggregateRootFinalStub::class));
    }

    public function testResolveAggregateTypeFromIoc(): void
    {
        $instance = new AggregateType(AggregateRootStub::class);

        $this->container->instance('aggregate_type.service', $instance);

        $factory = new AggregateTypeFactory(fn () => $this->container);

        $aggregateType = $factory->createType('aggregate_type.service');

        $this->assertInstanceOf(AggregateType::class, $aggregateType);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
    }
}
