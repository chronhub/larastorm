<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Illuminate\Database\Connection;
use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\EventStore\StoreDatabase;
use Chronhub\Larastorm\EventStore\MysqlEventStore;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\EventStore\Persistence\EventStream;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Larastorm\EventStore\StoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\ConnectionChroniclerProvider;
use Chronhub\Larastorm\EventStore\MysqlTransactionalEventStore;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Larastorm\EventStore\Persistence\SingleStreamPersistence;

final class MysqlEventStoreManagerTest extends OrchestraTestCase
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
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => 'single',
            'query_loader' => 'cursor',
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(MysqlTransactionalEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(StoreTransactionalDatabase::class, $concreteEventStore);

        /** @var Connection $connection */
        $connection = ReflectionProperty::getProperty($concreteEventStore, 'connection');
        $this->assertEquals('mysql', $connection->getDriverName());

        $writeLock = ReflectionProperty::getProperty($concreteEventStore, 'writeLock');
        $this->assertEquals(MysqlWriteLock::class, $writeLock::class);

        $streamPersistence = ReflectionProperty::getProperty($concreteEventStore, 'streamPersistence');
        $this->assertEquals(SingleStreamPersistence::class, $streamPersistence::class);

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
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => 'single',
            'query_loader' => 'cursor',
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(EventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(MysqlEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(StoreDatabase::class, $concreteEventStore);
    }

    /**
     * @test
     */
    public function it_return_standalone_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => 'single',
            'query_loader' => 'cursor',
            'is_transactional' => false,
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(MysqlEventStore::class, $eventStore::class);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(StoreDatabase::class, $eventStore->innerChronicler()::class);
    }

    /**
     * @test
     */
    public function it_return_transactional_standalone_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => 'single',
            'query_loader' => 'cursor',
            'is_transactional' => true,
        ]);

        $this->manager->shouldUse('connection', ConnectionChroniclerProvider::class);

        $eventStore = $this->manager->create('write');

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);

        $this->assertEquals(MysqlTransactionalEventStore::class, $eventStore::class);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(StoreTransactionalDatabase::class, $eventStore->innerChronicler()::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
