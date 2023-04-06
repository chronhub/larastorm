<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Projector\InMemoryQueryScope;
use Chronhub\Larastorm\Projection\ConnectionQueryScope;
use Chronhub\Storm\Contracts\Projector\ProjectionOption;
use Chronhub\Storm\Projector\InMemoryProjectionProvider;
use Chronhub\Larastorm\Projection\ProjectorServiceManager;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Support\Console\ReadProjectionCommand;
use Chronhub\Larastorm\Support\Console\WriteProjectionCommand;
use Chronhub\Storm\Projector\Options\InMemoryProjectionOption;
use Chronhub\Larastorm\Support\Console\Edges\ProjectAllStreamCommand;
use Chronhub\Larastorm\Support\Console\Edges\ProjectMessageNameCommand;
use Chronhub\Larastorm\Support\Console\Edges\ProjectStreamCategoryCommand;
use Chronhub\Larastorm\Support\Console\Generator\MakeQueryProjectionCommand;
use Chronhub\Larastorm\Support\Supervisor\Command\SuperviseProjectionCommand;
use Chronhub\Larastorm\Support\Console\Generator\MakeReadModelProjectionCommand;
use Chronhub\Larastorm\Support\Console\Generator\MakePersistentProjectionCommand;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager as ServiceManager;
use Chronhub\Larastorm\Support\Supervisor\Command\CheckSupervisedProjectionStatusCommand;

#[CoversClass(ProjectorServiceProvider::class)]
final class ProjectorServiceProviderTest extends OrchestraTestCase
{
    public function testConfiguration(): void
    {
        $this->assertEquals([
            'defaults' => [
                'projector' => 'connection',
            ],
            'providers' => [
                'connection' => [
                    'name' => 'pgsql',
                    'table' => 'projections',
                ],
                'in_memory' => InMemoryProjectionProvider::class,
            ],
            'projectors' => [
                'connection' => [
                    'default' => [
                        'chronicler' => ['connection', 'publish'],
                        'dispatcher' => true,
                        'options' => 'default',
                        'provider' => 'connection',
                        'scope' => ConnectionQueryScope::class,
                    ],
                    'emit' => [
                        'chronicler' => ['connection', 'read'],
                        'dispatcher' => true,
                        'options' => 'default',
                        'provider' => 'connection',
                        'scope' => ConnectionQueryScope::class,
                    ],
                ],
                'in_memory' => [
                    'testing' => [
                        'chronicler' => ['in_memory', 'standalone'],
                        'provider' => 'in_memory',
                        'options' => 'in_memory',
                        'scope' => InMemoryQueryScope::class,
                    ],
                ],
            ],
            'options' => [
                'default' => [],
                'lazy' => [
                    ProjectionOption::SIGNAL => true,
                    ProjectionOption::LOCKOUT => 500000,
                    ProjectionOption::SLEEP => 100000,
                    ProjectionOption::BLOCK_SIZE => 1000,
                    ProjectionOption::TIMEOUT => 10000,
                    ProjectionOption::RETRIES => '50, 1000, 50',
                    ProjectionOption::DETECTION_WINDOWS => null,
                ],
                'in_memory' => InMemoryProjectionOption::class,
                'snapshot' => [],
            ],
            'console' => [
                'load_migrations' => true,
                'commands' => [
                    ReadProjectionCommand::class,
                    WriteProjectionCommand::class,
                    MakePersistentProjectionCommand::class,
                    MakeReadModelProjectionCommand::class,
                    MakeQueryProjectionCommand::class,
                    SuperviseProjectionCommand::class,
                    CheckSupervisedProjectionStatusCommand::class,
                    ProjectAllStreamCommand::class,
                    ProjectStreamCategoryCommand::class,
                    ProjectMessageNameCommand::class,

                ],
            ],
        ], $this->app['config']['projector']);
    }

    public function testBindings(): void
    {
        $this->assertTrue($this->app->bound(ServiceManager::class));
        $this->assertEquals(ProjectorServiceManager::class, $this->app[ServiceManager::class]::class);
        $this->assertTrue($this->app->bound(Project::SERVICE_ID));
    }

    public function testProvides(): void
    {
        $serviceProvider = $this->app->getProvider(ProjectorServiceProvider::class);

        $this->assertEquals([
            ServiceManager::class,
            Project::SERVICE_ID,
        ], $serviceProvider->provides());
    }

    public function testConsoleCommands(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('projector:write', $commands);
        $this->assertArrayHasKey('projector:read', $commands);
        $this->assertArrayHasKey('projector:edge-all', $commands);
        $this->assertArrayHasKey('projector:edge-message-name', $commands);
        $this->assertArrayHasKey('projector:edge-category', $commands);
    }

    protected function getPackageProviders($app): array
    {
        return [ProjectorServiceProvider::class];
    }
}
