<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Chronhub\Larastorm\EventStore\EventStoreResolver;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Chronicler\InMemory\StandaloneInMemoryChronicler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(EventStoreResolver::class)]
final class EventStoreResolverTest extends OrchestraTestCase
{
    #[Test]
    public function it_create_event_store_instance_from_string_service(): void
    {
        $instance = Chronicle::setDefaultDriver('in_memory')->create('standalone');

        $this->app->instance('my_event_store', $instance);

        $resolver = new EventStoreResolver(fn () => $this->app);

        $eventStore = $resolver->resolve('my_event_store');

        $this->assertSame($instance, $eventStore);
    }

    #[Test]
    public function it_return_event_store_instance_from_array(): void
    {
        $resolver = new EventStoreResolver(fn () => $this->app);

        $eventStore = $resolver->resolve(['in_memory', 'standalone']);

        $this->assertEquals(StandaloneInMemoryChronicler::class, $eventStore::class);
    }

    #[Test]
    public function it_raise_exception_with_invalid_configuration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicler config bar is not defined');

        $resolver = new EventStoreResolver(fn () => $this->app);

        $resolver->resolve(['foo', 'bar']);
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }
}
