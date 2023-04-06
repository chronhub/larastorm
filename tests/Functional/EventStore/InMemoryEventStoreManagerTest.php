<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerFactory;
use Chronhub\Storm\Chronicler\InMemory\StandaloneInMemoryChronicler;
use Chronhub\Storm\Chronicler\InMemory\TransactionalInMemoryChronicler;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Storm\Contracts\Chronicler\TransactionalInMemoryChronicler as TransactionalInMemory;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventStoreManager::class)]
final class InMemoryEventStoreManagerTest extends OrchestraTestCase
{
    private EventStoreManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app[ChroniclerManager::class];
        $this->manager->setDefaultDriver('in_memory');

        $this->assertEquals('in_memory', $this->manager->getDefaultDriver());
        $this->assertEquals('in_memory', config('chronicler.defaults.provider'));
    }

    public function testStandaloneInstance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerFactory::class);

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);
    }

    public function testTransactionalInstance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerFactory::class);

        $eventStore = $this->manager->create('transactional');

        $this->assertInstanceOf(TransactionalInMemoryChronicler::class, $eventStore);
    }

    public function testEventableInstance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerFactory::class);

        $eventStore = $this->manager->create('eventable');

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore->innerChronicler());
    }

    public function testEventableTransactionalInstance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerFactory::class);

        $eventStore = $this->manager->create('transactional_eventable');

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(TransactionalInMemoryChronicler::class, $eventStore->innerChronicler());
        $this->assertInstanceOf(TransactionalInMemory::class, $eventStore->innerChronicler());
    }

    public function testReturnSameInstance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerFactory::class);

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);

        $this->assertSame($eventStore, $this->manager->create('standalone'));
    }

    public function testShouldUseChroniclerFactoryGiven(): void
    {
        $this->app['config']->set('chronicler.providers.foo',
            [
                'standalone' => [],
            ]);

        $this->manager->shouldUse('foo', new InMemoryChroniclerFactory(fn () => $this->app));

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);
        $this->assertEquals('foo', $this->manager->getDefaultDriver());
    }

    public function testExtendsManager(): void
    {
        $chronicler = $this->manager
            ->extend(
                'standalone',
                function (Container $app, string $name, array $config): Chronicler {
                    $this->assertEmpty($config);
                    $this->assertEquals('standalone', $name);

                    $provider = new InMemoryChroniclerFactory(fn () => $app);

                    return $provider->createEventStore($name, $config);
                })
            ->create('standalone');

        $this->assertEquals('in_memory', $this->manager->getDefaultDriver());
        $this->assertEquals(StandaloneInMemoryChronicler::class, $chronicler::class);
        $this->assertSame($chronicler, $this->manager->create('standalone'));
    }

    public function testExceptionRaisedWhenConfigNameIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicler config foo is not defined');

        $this->manager->create('foo');
    }

    public function testExceptionRaisedWhenChroniclerFactoryIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicler provider with name standalone and driver driver_not_set is not defined');

        $this->manager->setDefaultDriver('driver_not_set');

        $this->app['config']->set('chronicler.providers.driver_not_set',
            [
                'standalone' => [],
            ]);

        $this->manager->create('standalone');
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
