<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Illuminate\Container\Container;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Storm\Routing\CommandGroup;
use Chronhub\Storm\Producer\ProduceMessage;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Larastorm\Cqrs\MessageProducerFactory;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Routing\RouteCollection;
use Chronhub\Storm\Contracts\Producer\MessageProducer;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;

final class MessageProducerFactoryTest extends ProphecyTestCase
{
    private ObjectProphecy|RouteCollection $routes;

    protected function setUp(): void
    {
        $this->routes = $this->prophesize(RouteCollection::class);
    }

    /**
     * @test
     */
    public function it_create_message_producer_instance_from_group_calling_producer_service_id(): void
    {
        $container = Container::getInstance();

        $instance = $this->prophesize(MessageProducer::class)->reveal();
        $container->instance('message_producer.service', $instance);

        $group = new CommandGroup('default', $this->routes->reveal());
        $group->withProducerServiceId('message_producer.service');

        $factory = new MessageProducerFactory(fn () => $container);

        $this->assertSame($instance, $factory($group));
    }

    /**
     * @test
     */
    public function it_create_default_message_producer_instance_from_group_with_null_producer_service_id(): void
    {
        //fixMe phpUnit 10
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes->reveal());
        $this->assertNull($group->producerServiceId());

        $container
            ->expects($this->at(0))
            ->method('offsetGet')
            ->with(QueueingDispatcher::class)
            ->will(
                $this->returnValue($this->createMock(QueueingDispatcher::class))
            );

        $container
            ->expects($this->at(1))
            ->method('offsetGet')
            ->with(MessageSerializer::class)
            ->will(
                $this->returnValue($this->createMock(MessageSerializer::class))
            );

        $container
            ->expects($this->at(2))
            ->method('offsetGet')
            ->with(ProducerUnity::class)
            ->will(
                $this->returnValue($this->createMock(ProducerUnity::class))
            );

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertNull($queueOptions);
    }

    /**
     * @test
     */
    public function it_create_default_message_producer_instance_from_group_with_group_queue_options_and_null_producer_service_id(): void
    {
        //fixMe phpUnit 10
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes->reveal());
        $group->withQueue(['foo' => 'bar']);

        $this->assertNull($group->producerServiceId());

        $container
            ->expects($this->at(0))
            ->method('offsetGet')
            ->with(QueueingDispatcher::class)
            ->will(
                $this->returnValue($this->createMock(QueueingDispatcher::class))
            );

        $container
            ->expects($this->at(1))
            ->method('offsetGet')
            ->with(MessageSerializer::class)
            ->will(
                $this->returnValue($this->createMock(MessageSerializer::class))
            );

        $container
            ->expects($this->at(2))
            ->method('offsetGet')
            ->with(ProducerUnity::class)
            ->will(
                $this->returnValue($this->createMock(ProducerUnity::class))
            );

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertEquals(['foo' => 'bar'], $queueOptions);
    }
}
