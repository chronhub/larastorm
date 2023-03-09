<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Projector\InMemoryProjectorManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Projection\ProjectorServiceManager as ServiceManager;

#[CoversClass(Project::class)]
final class ProjectFacadeTest extends OrchestraTestCase
{
    #[Test]
    public function it_assert_root(): void
    {
        $root = Project::getFacadeRoot();

        $this->assertInstanceOf(ServiceManager::class, $root);
        $this->assertEquals(ServiceManager::class, $root::class);
    }

    #[Test]
    public function it_create_instance(): void
    {
        $manager = Project::setDefaultDriver('in_memory');

        $projectorManager = $manager->create('testing');

        $this->assertInstanceOf(InMemoryProjectorManager::class, $projectorManager);
    }

    #[Test]
    public function it_set_and_get_default_driver(): void
    {
        Project::setDefaultDriver('foo');

        $this->assertEquals('foo', Project::getDefaultDriver());
        $this->assertEquals('foo', config('projector.defaults.projector'));

        Project::setDefaultDriver('bar');

        $this->assertEquals('bar', Project::getDefaultDriver());
        $this->assertEquals('bar', config('projector.defaults.projector'));
    }

    #[Test]
    public function it_fix_facade_service_id(): void
    {
        $this->assertEquals('projector.service_manager', Project::SERVICE_ID);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
