<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Aggregate\AggregateRepositoryManager;
use Chronhub\Larastorm\Providers\AggregateRepositoryServiceProvider;
use Chronhub\Storm\Contracts\Aggregate\AggregateRepositoryManager as RepositoryManager;

#[CoversClass(AggregateRepositoryServiceProvider::class)]
class AggregateRepositoryServiceProviderTest extends OrchestraTestCase
{
    public function it_fix_configuration(): void
    {
        $this->assertEquals([
            'repository' => [
                'use_messager_decorators' => true,
                'event_decorators' => [],
                'repositories' => [
                    'my_stream_name' => [
                        'repository' => 'generic',
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
                        ], 'event_decorators' => [],
                    ],
                ],
            ],
        ], config('aggregate'));
    }

    #[Test]
    public function it_assert_bindings(): void
    {
        $this->assertTrue($this->app->bound(RepositoryManager::class));
        $this->assertInstanceOf(AggregateRepositoryManager::class, $this->app[RepositoryManager::class]);
    }

    #[Test]
    public function it_assert_provides(): void
    {
        $provider = $this->app->getProvider(AggregateRepositoryServiceProvider::class);

        $this->assertEquals([RepositoryManager::class], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [
            AggregateRepositoryServiceProvider::class,
        ];
    }
}
