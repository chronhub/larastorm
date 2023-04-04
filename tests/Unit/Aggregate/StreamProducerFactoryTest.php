<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Aggregate;

use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Stream\OneStreamPerAggregate;
use Chronhub\Storm\Stream\SingleStreamPerAggregate;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Aggregate\StreamProducerFactory;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

#[CoversClass(StreamProducerFactory::class)]
final class StreamProducerFactoryTest extends UnitTestCase
{
    public function testAssertSingleStreamProducer(): void
    {
        $factory = new StreamProducerFactory();

        $streamProducer = $factory->createStreamProducer('some_stream_name', 'single');

        $this->assertInstanceOf(SingleStreamPerAggregate::class, $streamProducer);

        $streamName = ReflectionProperty::getProperty($streamProducer, 'streamName');

        $this->assertEquals('some_stream_name', $streamName->name);
    }

    public function testAssertPerAggregateStreamProducer(): void
    {
        $factory = new StreamProducerFactory();

        $streamProducer = $factory->createStreamProducer('some_stream_name', 'per_aggregate');

        $this->assertInstanceOf(OneStreamPerAggregate::class, $streamProducer);

        $streamName = ReflectionProperty::getProperty($streamProducer, 'streamName');

        $this->assertEquals('some_stream_name', $streamName->name);
    }

    public function testExceptionRaisedWithInvalidStrategy(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Strategy given for stream name some_stream_name is not defined');

        $factory = new StreamProducerFactory();

        $factory->createStreamProducer('some_stream_name', 'nope');
    }
}
