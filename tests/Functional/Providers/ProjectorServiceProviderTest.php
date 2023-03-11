<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Artisan;
use Chronhub\Larastorm\Projection\Projection;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Projection\ConnectionQueryScope;
use Chronhub\Storm\Contracts\Projector\ProjectorOption;
use Chronhub\Larastorm\Projection\ProjectorServiceManager;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Storm\Projector\InMemoryQueryScope;
use Chronhub\Larastorm\Support\Console\ReadProjectionCommand;
use Chronhub\Storm\Projector\Options\InMemoryProjectorOption;
use Chronhub\Larastorm\Support\Console\WriteProjectionCommand;
use Chronhub\Storm\Projector\Provider\InMemoryProjectionProvider;
use Chronhub\Larastorm\Support\Console\Generator\MakeQueryProjectionCommand;
use Chronhub\Larastorm\Support\Supervisor\Command\SuperviseProjectionCommand;
use Chronhub\Larastorm\Support\Console\Generator\MakeReadModelProjectionCommand;
use Chronhub\Larastorm\Support\Console\Generator\MakePersistentProjectionCommand;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager as ServiceManager;
use Chronhub\Larastorm\Support\Supervisor\Command\CheckSupervisedProjectionStatusCommand;

#[CoversClass(ProjectorServiceProvider::class)]
final class ProjectorServiceProviderTest extends OrchestraTestCase
{
    #[Test]
    public function it_assert_config(): void
    {
        $this->assertEquals([
            'defaults' => [
                'projector' => 'connection',
            ],
            'providers' => [
                'eloquent' => Projection::class,
                'in_memory' => InMemoryProjectionProvider::class,
            ],
            'projectors' => [
                'connection' => [
                    'default' => [
                        'chronicler' => ['connection', 'write'],
                        'dispatcher' => true,
                        'options' => 'default',
                        'provider' => 'eloquent',
                        'scope' => ConnectionQueryScope::class,
                    ],
                    'emit' => [
                        'chronicler' => ['connection', 'read'],
                        'dispatcher' => true,
                        'options' => 'default',
                        'provider' => 'eloquent',
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
                    ProjectorOption::SIGNAL => true,
                    ProjectorOption::LOCKOUT => 500000,
                    ProjectorOption::SLEEP => 100000,
                    ProjectorOption::BLOCK_SIZE => 1000,
                    ProjectorOption::TIMEOUT => 10000,
                    ProjectorOption::RETRIES => '50, 1000, 50',
                    ProjectorOption::DETECTION_WINDOWS => null,
                ],
                'in_memory' => InMemoryProjectorOption::class,
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
                ],
            ],
        ], $this->app['config']['projector']);
    }

    #[Test]
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(ServiceManager::class));
        $this->assertEquals(ProjectorServiceManager::class, $this->app[ServiceManager::class]::class);
        $this->assertTrue($this->app->bound(Project::SERVICE_ID));
    }

    #[Test]
    public function it_assert_provides(): void
    {
        $serviceProvider = $this->app->getProvider(ProjectorServiceProvider::class);

        $this->assertEquals([
            ServiceManager::class,
            Project::SERVICE_ID,
        ], $serviceProvider->provides());
    }

    #[Test]
    public function it_assert_console_commands_registered(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('projector:write', $commands);
        $this->assertArrayHasKey('projector:read', $commands);
    }

    protected function getPackageProviders($app): array
    {
        return [ProjectorServiceProvider::class];
    }
}
