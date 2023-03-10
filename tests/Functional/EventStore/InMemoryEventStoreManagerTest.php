<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Chronhub\Storm\Chronicler\InMemory\StandaloneInMemoryChronicler;
use Chronhub\Storm\Chronicler\InMemory\TransactionalInMemoryChronicler;
use Chronhub\Storm\Contracts\Chronicler\TransactionalInMemoryChronicler as TransactionalInMemory;

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

    #[Test]
    public function it_return_standalone_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);
    }

    #[Test]
    public function it_return_transactional_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('transactional');

        $this->assertInstanceOf(TransactionalInMemoryChronicler::class, $eventStore);
    }

    #[Test]
    public function it_return_eventable_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('eventable');

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore->innerChronicler());
    }

    #[Test]
    public function it_return_transactional_eventable_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('transactional_eventable');

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(TransactionalInMemoryChronicler::class, $eventStore->innerChronicler());
        $this->assertInstanceOf(TransactionalInMemory::class, $eventStore->innerChronicler());
    }

    #[Test]
    public function it_always_return_same_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);

        $this->assertSame($eventStore, $this->manager->create('standalone'));
    }

    #[Test]
    public function it_should_use_provider_given(): void
    {
        $this->app['config']->set('chronicler.providers.foo',
            [
                'standalone' => [],
            ]);

        $this->manager->shouldUse('foo', new InMemoryChroniclerProvider(fn () => $this->app));

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);
        $this->assertEquals('foo', $this->manager->getDefaultDriver());
    }

    #[Test]
    public function it_extends_manager_and_return_chronicler_instance(): void
    {
        $chronicler = $this->manager
            ->extend(
                'standalone',
                function (Container $app, string $name, array $config): Chronicler {
                    $this->assertEmpty($config);
                    $this->assertEquals('standalone', $name);

                    $provider = new InMemoryChroniclerProvider(fn () => $app);

                    return $provider->resolve($name, $config);
                })
            ->create('standalone');

        $this->assertEquals('in_memory', $this->manager->getDefaultDriver());
        $this->assertEquals(StandaloneInMemoryChronicler::class, $chronicler::class);
        $this->assertSame($chronicler, $this->manager->create('standalone'));
    }

    #[Test]
    public function it_raise_exception_when_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicler config foo is not defined');

        $this->manager->create('foo');
    }

    #[Test]
    public function it_raise_exception_when_provider_driver_is_unknown(): void
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
