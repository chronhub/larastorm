<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Larastorm\Providers\AggregateRepositoryServiceProvider;
use Chronhub\Larastorm\Support\Contracts\AggregateRepositoryManager as Manager;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AggregateRepositoryServiceProvider::class)]
class AggregateRepositoryServiceProviderTest extends OrchestraTestCase
{
    public function testConfiguration(): void
    {
        $this->assertEquals([
            'repository' => [
                'use_messager_decorators' => true,
                'event_decorators' => [],
                'repositories' => [
                    'my_stream_name' => [
                        'chronicler' => ['connection', 'write'],
                        'strategy' => 'single',
                        'aggregate_type' => [
                            'root' => 'aggregate root class name',
                            'lineage' => [],
                        ],
                        'cache' => [
                            'size' => 0,
                            'tag' => null,
                            'driver' => null,
                        ],
                        'event_decorators' => [],
                        'support_snapshot' => false,
                    ],
                ],
            ],
        ], config('aggregate'));
    }

    public function testBindings(): void
    {
        $this->assertTrue($this->app->bound(Manager::class));
        $this->assertInstanceOf(AggregateRepositoryManager::class, $this->app[Manager::class]);
    }

    public function testProvides(): void
    {
        $provider = $this->app->getProvider(AggregateRepositoryServiceProvider::class);

        $this->assertEquals([Manager::class], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            AggregateRepositoryServiceProvider::class,
        ];
    }
}
