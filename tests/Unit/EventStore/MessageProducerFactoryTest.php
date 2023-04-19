<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\Cqrs\MessageProducerFactory;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Producer\MessageProducer;
use Chronhub\Storm\Contracts\Producer\MessageQueue;
use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Contracts\Routing\RouteCollection;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Producer\ProduceMessage;
use Chronhub\Storm\Producer\ProducerStrategy;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Routing\Group;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(MessageProducerFactory::class)]
final class MessageProducerFactoryTest extends UnitTestCase
{
    private MockObject|RouteCollection $routes;

    protected function setUp(): void
    {
        $this->routes = $this->createMock(RouteCollection::class);
    }

    #[DataProvider('provideDomainType')]
    public function testDefaultInstance(DomainType $domainType): void
    {
        $container = $this->createMock(Container::class);

        $group = new Group($domainType, 'default', $this->routes);
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

    #[DataProvider('provideDomainType')]
    public function testInstanceResolvedFromIoc(DomainType $domainType): void
    {
        $instance = $this->createMock(MessageProducer::class);

        $container = Container::getInstance();
        $container->instance('message_producer.service', $instance);

        $group = new Group($domainType, 'default', $this->routes);
        $group->withProducerId('message_producer.service');

        $factory = new MessageProducerFactory(fn () => $container);

        $this->assertSame($instance, $factory->createMessageProducer($group));
    }

    #[DataProvider('provideDomainType')]
    public function testInstanceWithDefinedQueueOptions(DomainType $domainType): void
    {
        $container = $this->createMock(Container::class);

        $group = new Group($domainType, 'default', $this->routes);
        $group->withStrategy(ProducerStrategy::ASYNC->value);
        $group->withQueue(['foo' => 'bar']);

        $this->assertNull($group->producerId());
        $this->expectMessageQueueResolved($container);

        $factory = new MessageProducerFactory(static fn () => $container);
        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertEquals(IlluminateQueue::class, $queueDispatcher::class);

        $queueOptions = ReflectionProperty::getProperty($queueDispatcher, 'groupQueueOptions');
        $this->assertEquals(['foo' => 'bar'], $queueOptions);
    }

    #[DataProvider('provideDomainType')]
    public function testMessageQueueNotSetWhenProducerStrategyIsSync(DomainType $domainType): void
    {
        $container = $this->createMock(Container::class);

        $group = new Group($domainType, 'default', $this->routes);
        $group->withStrategy(ProducerStrategy::SYNC->value);

        $container->expects($this->once())
            ->method('offsetGet')
            ->with(ProducerUnity::class)
            ->willReturn($this->createMock(ProducerUnity::class));

        $factory = new MessageProducerFactory(static fn () => $container);

        $messageProducer = $factory->createMessageProducer($group);

        $this->assertEquals(ProduceMessage::class, $messageProducer::class);

        $queueDispatcher = ReflectionProperty::getProperty($messageProducer, 'enqueue');
        $this->assertNull($queueDispatcher);
    }

    #[DataProvider('provideNotSyncStrategy')]
    public function testMessageQueue(string $notSyncStrategy): void
    {
        $container = $this->createMock(Container::class);

        $group = new Group(DomainType::COMMAND, 'default', $this->routes);
        $group->withStrategy($notSyncStrategy);

        $this->expectMessageQueueResolved($container);

        $factory = new MessageProducerFactory(static fn () => $container);

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

    public static function provideDomainType(): Generator
    {
        yield [DomainType::COMMAND];
        yield [DomainType::EVENT];
        yield [DomainType::QUERY];

    }

    private function expectMessageQueueResolved(Container|MockObject $container): void
    {
        $container
            ->expects($this->exactly(3))
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
