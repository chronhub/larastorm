<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Contracts\Chronicler\InMemoryChronicler;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerProvider;
use Chronhub\Storm\Chronicler\InMemory\StandaloneInMemoryChronicler;

#[CoversClass(Chronicle::class)]
final class ChronicleFacadeTest extends OrchestraTestCase
{
    #[Test]
    public function it_test_facade_root(): void
    {
        $root = Chronicle::getFacadeRoot();

        $this->assertInstanceOf(ChroniclerManager::class, $root);
        $this->assertEquals(EventStoreManager::class, $root::class);
    }

    #[Test]
    public function it_create_instance(): void
    {
        $manager = Chronicle::shouldUse('in_memory', InMemoryChroniclerProvider::class);

        $eventStore = $manager->create('standalone');

        $this->assertInstanceOf(InMemoryChronicler::class, $eventStore);
        $this->assertEquals(StandaloneInMemoryChronicler::class, $eventStore::class);
    }

    #[Test]
    public function it_set_and_get_default_driver(): void
    {
        Chronicle::setDefaultDriver('foo');

        $this->assertEquals('foo', Chronicle::getDefaultDriver());
        $this->assertEquals('foo', config('chronicler.defaults.provider'));

        Chronicle::setDefaultDriver('bar');

        $this->assertEquals('bar', Chronicle::getDefaultDriver());
        $this->assertEquals('bar', config('chronicler.defaults.provider'));
    }

    #[Test]
    public function it_fix_facade_service_id(): void
    {
        $this->assertEquals('chronicler.manager', Chronicle::SERVICE_ID);
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }
}
