<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Chronicler\InMemory\InMemoryChroniclerFactory;
use Chronhub\Storm\Chronicler\InMemory\StandaloneInMemoryChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Chronicler\InMemoryChronicler;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Chronicle::class)]
final class ChronicleFacadeTest extends OrchestraTestCase
{
    public function testFacadeRoot(): void
    {
        $root = Chronicle::getFacadeRoot();

        $this->assertInstanceOf(ChroniclerManager::class, $root);
        $this->assertEquals(EventStoreManager::class, $root::class);
    }

    public function testInstance(): void
    {
        $manager = Chronicle::shouldUse('in_memory', InMemoryChroniclerFactory::class);

        $eventStore = $manager->create('standalone');

        $this->assertInstanceOf(InMemoryChronicler::class, $eventStore);
        $this->assertEquals(StandaloneInMemoryChronicler::class, $eventStore::class);
    }

    public function testDefaultDriver(): void
    {
        Chronicle::setDefaultDriver('foo');

        $this->assertEquals('foo', Chronicle::getDefaultDriver());
        $this->assertEquals('foo', config('chronicler.defaults.provider'));

        Chronicle::setDefaultDriver('bar');

        $this->assertEquals('bar', Chronicle::getDefaultDriver());
        $this->assertEquals('bar', config('chronicler.defaults.provider'));
    }

    public function testServiceId(): void
    {
        $this->assertEquals('chronicler.manager', Chronicle::SERVICE_ID);
    }

    protected function getPackageProviders($app): array
    {
        return [ChroniclerServiceProvider::class];
    }
}
