<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Routing\CommandGroup;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Producer\ProduceMessage;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Larastorm\Cqrs\MessageProducerFactory;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Routing\RouteCollection;
use Chronhub\Storm\Contracts\Producer\MessageProducer;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;

#[CoversClass(MessageProducerFactory::class)]
final class MessageProducerFactoryTest extends UnitTestCase
{
    private MockObject|RouteCollection $routes;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->routes = $this->createMock(RouteCollection::class);
    }

    #[Test]
    public function it_create_message_producer_instance_from_group_calling_producer_service_id(): void
    {
        $instance = $this->createMock(MessageProducer::class);

        $container = Container::getInstance();
        $container->instance('message_producer.service', $instance);

        $group = new CommandGroup('default', $this->routes);
        $group->withProducerServiceId('message_producer.service');

        $factory = new MessageProducerFactory(fn () => $container);

        $this->assertSame($instance, $factory->createMessageProducer($group));
    }

    #[Test]
    public function it_create_default_message_producer_instance_from_group_with_null_producer_service_id(): void
    {
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes);
        $this->assertNull($group->producerServiceId());

        $container->expects($this->exactly(3))
            ->method('offsetGet')
            ->willReturnMap(
                [
                    [QueueingDispatcher::class, $this->createMock(QueueingDispatcher::class)],
                    [MessageSerializer::class, $this->createMock(MessageSerializer::class)],
                    [ProducerUnity::class, $this->createMock(ProducerUnity::class)],
                ]
            );

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertNull($queueOptions);
    }

    #[Test]
    public function it_create_default_message_producer_instance_from_group_with_group_queue_options_and_null_producer_service_id(): void
    {
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes);
        $group->withQueue(['foo' => 'bar']);

        $this->assertNull($group->producerServiceId());

        $container->expects($this->exactly(3))
            ->method('offsetGet')
            ->willReturnMap(
                [
                    [QueueingDispatcher::class, $this->createMock(QueueingDispatcher::class)],
                    [MessageSerializer::class, $this->createMock(MessageSerializer::class)],
                    [ProducerUnity::class, $this->createMock(ProducerUnity::class)],
                ]
            );

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertEquals(['foo' => 'bar'], $queueOptions);
    }
}
