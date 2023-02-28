<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Aggregate;

use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Stream\OneStreamPerAggregate;
use Chronhub\Storm\Stream\SingleStreamPerAggregate;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Aggregate\StreamProducerFactory;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

final class StreamProducerFactoryTest extends UnitTestCase
{
    /**
     * @test
     */
    public function it_assert_single_stream_producer(): void
    {
        $factory = new StreamProducerFactory();

        $streamProducer = $factory->createStreamProducer('some_stream_name', 'single');

        $this->assertInstanceOf(SingleStreamPerAggregate::class, $streamProducer);

        $streamName = ReflectionProperty::getProperty($streamProducer, 'streamName');

        $this->assertEquals('some_stream_name', $streamName->name);
    }

    /**
     * @test
     */
    public function it_assert_per_aggregate_stream_producer(): void
    {
        $factory = new StreamProducerFactory();

        $streamProducer = $factory->createStreamProducer('some_stream_name', 'per_aggregate');

        $this->assertInstanceOf(OneStreamPerAggregate::class, $streamProducer);

        $streamName = ReflectionProperty::getProperty($streamProducer, 'streamName');

        $this->assertEquals('some_stream_name', $streamName->name);
    }

    /**
     * @test
     */
    public function it_raise_exception_with_unknown_strategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Strategy given for stream name some_stream_name is not defined');

        $factory = new StreamProducerFactory();

        $factory->createStreamProducer('some_stream_name', 'nope');
    }
}
