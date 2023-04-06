<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use Chronhub\Larastorm\Projection\ProjectorServiceManager as ServiceManager;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Projector\ProjectorManager;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Project::class)]
final class ProjectFacadeTest extends OrchestraTestCase
{
    public function testFacadeRoot(): void
    {
        $root = Project::getFacadeRoot();

        $this->assertInstanceOf(ServiceManager::class, $root);
        $this->assertEquals(ServiceManager::class, $root::class);
    }

    public function testInstance(): void
    {
        $manager = Project::setDefaultDriver('in_memory');

        $projectorManager = $manager->create('testing');

        $this->assertInstanceOf(ProjectorManager::class, $projectorManager);
    }

    public function testDefaultDriverGetterAndSetter(): void
    {
        Project::setDefaultDriver('foo');

        $this->assertEquals('foo', Project::getDefaultDriver());
        $this->assertEquals('foo', config('projector.defaults.projector'));

        Project::setDefaultDriver('bar');

        $this->assertEquals('bar', Project::getDefaultDriver());
        $this->assertEquals('bar', config('projector.defaults.projector'));
    }

    public function testServiceId(): void
    {
        $this->assertEquals('projector.service_manager', Project::SERVICE_ID);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
