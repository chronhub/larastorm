<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Providers;

use Chronhub\Larastorm\Providers\SnapshotServiceProvider;
use Chronhub\Larastorm\Snapshot\SnapshotStoreManager as ConcreteSnapshotStoreManager;
use Chronhub\Larastorm\Support\Contracts\SnapshotStoreManager;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Support\Facades\Artisan;

final class SnapshotServiceProviderTest extends OrchestraTestCase
{
    public function testConfiguration(): void
    {
        $this->assertEquals([
            'connection' => [
                'default' => 'pgsql',
                'table_name' => 'snapshots',
                'mapping_tables' => false,
                'suffix' => '_snapshot',
                'query_scope' => \Chronhub\Larastorm\Snapshot\ConnectionSnapshotQueryScope::class,
                'serializer' => \Chronhub\Storm\Snapshot\Base64EncodeSerializer::class,
                'console' => [
                    'load_migration' => true,
                    'commands' => [
                        \Chronhub\Larastorm\Snapshot\SnapshotMappingTablesMigrationCommand::class,
                        \Chronhub\Larastorm\Snapshot\ProjectSnapshotReadModelCommand::class,
                    ],
                ],
            ],
            'in_memory' => [
                'query_scope' => \Chronhub\Storm\Snapshot\InMemorySnapshotQueryScope::class,
            ],
        ], config('snapshot'));
    }

    public function testBindings(): void
    {
        $this->assertTrue($this->app->bound(SnapshotStoreManager::class));
        $this->assertEquals(ConcreteSnapshotStoreManager::class, $this->app[SnapshotStoreManager::class]::class);
    }

    public function testCommands(): void
    {
        $commands = Artisan::all();

        $this->assertArrayHasKey('snapshot:migrate', $commands);
    }

    public function testProvides(): void
    {
        $provider = $this->app->getProvider(SnapshotServiceProvider::class);

        $this->assertEquals([SnapshotStoreManager::class], $provider->provides());
    }

    protected function getPackageProviders($app): array
    {
        return [SnapshotServiceProvider::class];
    }
}
