<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Aggregate;

use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Aggregate\AggregateType;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootStub;
use Chronhub\Larastorm\Aggregate\AggregateTypeFactory;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootChildStub;
use Chronhub\Larastorm\Tests\Stubs\AggregateRootFinalStub;

#[CoversClass(AggregateTypeFactory::class)]
final class AggregateTypeFactoryTest extends UnitTestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = Container::getInstance();
    }

    #[Test]
    public function it_return_aggregate_type_from_string_aggregate_root_class_name(): void
    {
        $factory = new AggregateTypeFactory(fn () => $this->container);

        $aggregateType = $factory->createType(AggregateRootStub::class);

        $this->assertInstanceOf(AggregateType::class, $aggregateType);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
    }

    #[Test]
    public function it_return_aggregate_type_from_string_service_id(): void
    {
        $instance = new AggregateType(AggregateRootStub::class);

        $this->container->instance('aggregate_type.service', $instance);

        $factory = new AggregateTypeFactory(fn () => $this->container);

        $aggregateType = $factory->createType('aggregate_type.service');

        $this->assertInstanceOf(AggregateType::class, $aggregateType);

        $this->assertEquals(AggregateRootStub::class, $aggregateType->current());
    }

    #[Test]
    public function it_return_aggregate_type_from_array(): void
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
}
