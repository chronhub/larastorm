<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Routing\CommandGroup;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Producer\ProduceMessage;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Chronhub\Storm\Producer\ProducerStrategy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Larastorm\Cqrs\MessageProducerFactory;
use Chronhub\Storm\Contracts\Producer\MessageQueue;
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
    public function it_create_default_message_producer(): void
    {
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes);
        $group->withStrategy(ProducerStrategy::PER_MESSAGE->value);

        $this->assertNull($group->producerId());

        $this->expectMessageQueueResolved($container);

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertNull($queueOptions);
    }

    #[Test]
    public function it_create_message_producer_instance_resolved_from_ioc(): void
    {
        $instance = $this->createMock(MessageProducer::class);

        $container = Container::getInstance();
        $container->instance('message_producer.service', $instance);

        $group = new CommandGroup('default', $this->routes);
        $group->withProducerId('message_producer.service');

        $factory = new MessageProducerFactory(fn () => $container);

        $this->assertSame($instance, $factory->createMessageProducer($group));
    }

    #[Test]
    public function it_create_default_message_producer_instance_with_queue_options_defined(): void
    {
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes);
        $group->withStrategy(ProducerStrategy::ASYNC->value);
        $group->withQueue(['foo' => 'bar']);

        $this->assertNull($group->producerId());
        $this->expectMessageQueueResolved($container);

        $factory = new MessageProducerFactory(fn () => $container);
        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertEquals(['foo' => 'bar'], $queueOptions);
    }

    #[Test]
    public function message_queue_should_no_be_set_when_group_strategy_is_sync(): void
    {
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes);
        $group->withStrategy(ProducerStrategy::SYNC->value);

        $container->expects($this->once())
            ->method('offsetGet')
            ->with(ProducerUnity::class)
            ->willReturn($this->createMock(ProducerUnity::class));

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertNull($queueDispatcher);
    }

    #[Test]
    #[DataProvider('provideNotSyncStrategy')]
    public function message_queue_should_be_set_when_group_strategy_is_not_sync(string $notSyncStrategy): void
    {
        $container = $this->createMock(Container::class);

        $group = new CommandGroup('default', $this->routes);
        $group->withStrategy($notSyncStrategy);

        $this->expectMessageQueueResolved($container);

        $factory = new MessageProducerFactory(fn () => $container);

        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertInstanceOf(MessageQueue::class, $queueDispatcher);
    }

    public static function provideNotSyncStrategy(): Generator
    {
        yield [ProducerStrategy::ASYNC->value];
        yield [ProducerStrategy::PER_MESSAGE->value];
    }

    private function expectMessageQueueResolved(Container|MockObject $container): void
    {
        $container->expects($this->exactly(3))
            ->method('offsetGet')
            ->willReturnMap(
                [
                    [QueueingDispatcher::class, $this->createMock(QueueingDispatcher::class)],
                    [MessageSerializer::class, $this->createMock(MessageSerializer::class)],
                    [ProducerUnity::class, $this->createMock(ProducerUnity::class)],
                ]
            );
    }
}
