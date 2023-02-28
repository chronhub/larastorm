<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Illuminate\Database\Connection;
use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\EventStore\PgsqlEventStore;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Chronhub\Larastorm\EventStore\PgsqlTransactionalEventStore;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;

final class PgsqlEventStoreManagerTest extends OrchestraTestCase
{
    private EventStoreManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app[ChroniclerManager::class];

        $this->assertEquals('connection', $this->manager->getDefaultDriver());
        $this->assertEquals('connection', config('chronicler.defaults.provider'));
    }

    /**
     * @test
     */
    public function it_return_transactional_eventable_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(PgsqlTransactionalEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $concreteEventStore);

        /** @var Connection $connection */
        $connection = ReflectionProperty::getProperty($concreteEventStore, 'connection');
        $this->assertEquals('pgsql', $connection->getDriverName());

        $writeLock = ReflectionProperty::getProperty($concreteEventStore, 'writeLock');
        $this->assertEquals(PgsqlWriteLock::class, $writeLock::class);

        $streamPersistence = ReflectionProperty::getProperty($concreteEventStore, 'streamPersistence');
        $this->assertEquals(PgsqlSingleStreamPersistence::class, $streamPersistence::class);

        $eventLoader = ReflectionProperty::getProperty($concreteEventStore, 'eventLoader');
        $this->assertEquals(CursorQueryLoader::class, $eventLoader::class);

        // checkMe last two not part of config
        $eventStreamProvider = ReflectionProperty::getProperty($concreteEventStore, 'eventStreamProvider');
        $this->assertInstanceOf(EventStream::class, $eventStreamProvider);

        $streamCategory = ReflectionProperty::getProperty($concreteEventStore, 'streamCategory');
        $this->assertInstanceOf(DetermineStreamCategory::class, $streamCategory);
    }

    /**
     * @test
     */
    public function it_return_eventable_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(EventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(PgsqlEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(EventStoreDatabaseDatabase::class, $concreteEventStore);
    }

    /**
     * @test
     */
    public function it_return_standalone_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
            'is_transactional' => false,
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(PgsqlEventStore::class, $eventStore::class);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(EventStoreDatabaseDatabase::class, $eventStore->innerChronicler()::class);
    }

    /**
     * @test
     */
    public function it_return_transactional_standalone_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
            'is_transactional' => true,
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);

        $this->assertEquals(PgsqlTransactionalEventStore::class, $eventStore::class);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(EventStoreTransactionalDatabase::class, $eventStore->innerChronicler()::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
