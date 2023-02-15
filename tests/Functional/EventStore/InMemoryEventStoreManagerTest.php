<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Illuminate\Contracts\Container\Container;
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

    /**
     * @test
     */
    public function it_return_standalone_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);
    }

    /**
     * @test
     */
    public function it_return_transactional_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('transactional');

        $this->assertInstanceOf(TransactionalInMemoryChronicler::class, $eventStore);
    }

    /**
     * @test
     */
    public function it_return_eventable_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('eventable');

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore->innerChronicler());
    }

    /**
     * @test
     */
    public function it_return_transactional_eventable_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('transactional_eventable');

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(TransactionalInMemoryChronicler::class, $eventStore->innerChronicler());
        $this->assertInstanceOf(TransactionalInMemory::class, $eventStore->innerChronicler());
    }

    /**
     * @test
     */
    public function it_always_return_same_instance(): void
    {
        $this->manager->shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $this->manager->create('standalone');

        $this->assertInstanceOf(StandaloneInMemoryChronicler::class, $eventStore);

        $this->assertSame($eventStore, $this->manager->create('standalone'));
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

    /**
     * @test
     */
    public function it_raise_exception_when_name_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicler config foo is not defined');

        $this->manager->create('foo');
    }

    /**
     * @test
     */
    public function it_raise_exception_when_provider_driver_is_unknown(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicler provider with name foo_bar and driver in_memory is not defined');

        $this->app['config']->set('chronicler.providers.in_memory',
            [
                'foo_bar' => [],
            ]);

        $this->manager->create('foo_bar');
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
