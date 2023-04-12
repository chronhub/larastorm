<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Projection\ConnectionRepository;
use Chronhub\Larastorm\Projection\ConnectionSubscriptionFactory;
use Chronhub\Larastorm\Projection\DispatcherAwareRepository;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Message\MessageAlias;
use Chronhub\Storm\Contracts\Projector\ProjectionOption;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Contracts\Serializer\JsonSerializer;
use Chronhub\Storm\Projector\EmitterSubscription;
use Chronhub\Storm\Projector\Options\DefaultProjectionOption;
use Chronhub\Storm\Projector\ReadModelSubscription;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\MockObject\MockObject;

final class ConnectionSubscriptionFactoryTest extends UnitTestCase
{
    private Chronicler|MockObject $chronicler;

    private EventStreamProvider|MockObject $eventStreamProvider;

    private ProjectionProvider|MockObject $projectionProvider;

    private ProjectionQueryScope|MockObject $queryScope;

    private SystemClock|MockObject $clock;

    private JsonSerializer|MockObject $jsonSerializer;

    private MessageAlias|MockObject $messageAlias;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chronicler = $this->createMock(Chronicler::class);
        $this->eventStreamProvider = $this->createMock(EventStreamProvider::class);
        $this->projectionProvider = $this->createMock(ProjectionProvider::class);
        $this->queryScope = $this->createMock(ProjectionQueryScope::class);
        $this->clock = $this->createMock(SystemClock::class);
        $this->jsonSerializer = $this->createMock(JsonSerializer::class);
        $this->messageAlias = $this->createMock(MessageAlias::class);
    }

    public function testCreateEmitterSubscription(): void
    {
        $factory = $this->subscriptionFactory(new DefaultProjectionOption());

        $subscription = $factory->createEmitterSubscription('foo');

        $this->assertSame(EmitterSubscription::class, $subscription::class);

        $repository = ReflectionProperty::getProperty($subscription, 'repository');
        $this->assertInstanceOf(ConnectionRepository::class, $repository);
    }

    public function testCreateEmitterSubscriptionWithEventDispatcher(): void
    {
        $factory = $this->subscriptionFactory(new DefaultProjectionOption());
        $factory->setEventDispatcher($this->createMock(Dispatcher::class));

        $subscription = $factory->createEmitterSubscription('foo');
        $this->assertSame(EmitterSubscription::class, $subscription::class);

        $repository = ReflectionProperty::getProperty($subscription, 'repository');
        $this->assertInstanceOf(DispatcherAwareRepository::class, $repository);
    }

    public function testCreateReadModelSubscription(): void
    {
        $readModel = $this->createMock(ReadModel::class);

        $factory = $this->subscriptionFactory(new DefaultProjectionOption());

        $subscription = $factory->createReadModelSubscription('foo', $readModel);
        $this->assertSame(ReadModelSubscription::class, $subscription::class);

        $repository = ReflectionProperty::getProperty($subscription, 'repository');
        $this->assertInstanceOf(ConnectionRepository::class, $repository);
    }

    public function testCreateReadModelSubscriptionWithEventDispatcher(): void
    {
        $readModel = $this->createMock(ReadModel::class);

        $factory = $this->subscriptionFactory(new DefaultProjectionOption());
        $factory->setEventDispatcher($this->createMock(Dispatcher::class));

        $subscription = $factory->createReadModelSubscription('foo', $readModel);
        $this->assertSame(ReadModelSubscription::class, $subscription::class);

        $repository = ReflectionProperty::getProperty($subscription, 'repository');
        $this->assertInstanceOf(DispatcherAwareRepository::class, $repository);
    }

    private function subscriptionFactory(ProjectionOption $projectorOption = null): ConnectionSubscriptionFactory
    {
       return new ConnectionSubscriptionFactory(
            $this->chronicler,
            $this->projectionProvider,
            $this->eventStreamProvider,
            $this->queryScope,
            $this->clock,
            $this->messageAlias,
            $this->jsonSerializer,
            $projectorOption
        );
    }
}
