<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Illuminate\Support\Facades\Artisan;
use Chronhub\Larastorm\Projection\Projection;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Projector\ProjectorOption;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Storm\Projector\InMemoryProjectionQueryScope;
use Chronhub\Larastorm\Support\Console\ReadProjectionCommand;
use Chronhub\Storm\Projector\Options\InMemoryProjectorOption;
use Chronhub\Larastorm\Support\Console\WriteProjectionCommand;
use Chronhub\Storm\Contracts\Projector\ProjectorServiceManager;
use Chronhub\Larastorm\Projection\ConnectionProjectionQueryScope;
use Chronhub\Larastorm\Projection\ProvideProjectorServiceManager;
use Chronhub\Storm\Projector\Provider\InMemoryProjectionProvider;

final class ProjectorServiceProviderTest extends OrchestraTestCase
{
    /**
     * @test
     */
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
                        'options' => 'default',
                        'provider' => 'eloquent',
                        'scope' => ConnectionProjectionQueryScope::class,
                    ],
                    'emit' => [
                        'chronicler' => ['connection', 'read'],
                        'options' => 'default',
                        'provider' => 'eloquent',
                        'scope' => ConnectionProjectionQueryScope::class,
                    ],
                ],
                'in_memory' => [
                    'testing' => [
                        'chronicler' => ['in_memory', 'standalone'],
                        'provider' => 'in_memory',
                        'options' => 'in_memory',
                        'scope' => InMemoryProjectionQueryScope::class,
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
                ],
            ],
        ], $this->app['config']['projector']);
    }

    /**
     * @test
     */
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(ProjectorServiceManager::class));
        $this->assertEquals(ProvideProjectorServiceManager::class, $this->app[ProjectorServiceManager::class]::class);
        $this->assertTrue($this->app->bound(Project::SERVICE_ID));
    }

    /**
     * @test
     */
    public function it_assert_provides(): void
    {
        $serviceProvider = $this->app->getProvider(ProjectorServiceProvider::class);

        $this->assertEquals([
            ProjectorServiceManager::class,
            Project::SERVICE_ID,
        ], $serviceProvider->provides());
    }

    /**
     * @test
     */
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
