<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Illuminate\Database\Connection;
use Chronhub\Storm\Chronicler\TrackStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\EventStore\PgsqlEventStore;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\WriteLock\PgsqlWriteLock;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Larastorm\EventStore\PgsqlTransactionalEventStore;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;

#[CoversClass(EventStoreManager::class)]
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

    public function testTransactionalEventableInstance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publisher', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publisher');

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

        $eventStreamProvider = ReflectionProperty::getProperty($concreteEventStore, 'eventStreamProvider');
        $this->assertInstanceOf(EventStreamProvider::class, $eventStreamProvider);

        $streamCategory = ReflectionProperty::getProperty($concreteEventStore, 'streamCategory');
        $this->assertInstanceOf(DetermineStreamCategory::class, $streamCategory);
    }

    public function testEventableInstance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publisher', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publisher');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(EventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(PgsqlEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(EventStoreDatabase::class, $concreteEventStore);
    }

    public function testStandaloneInstance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publisher', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => null,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
            'is_transactional' => false,
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publisher');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(PgsqlEventStore::class, $eventStore::class);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(EventStoreDatabase::class, $eventStore->innerChronicler()::class);
    }

    public function testTransactionalStandalone(): void
    {
        $this->app['config']->set('chronicler.providers.connection.write', [
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => null,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
            'is_transactional' => true,
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

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
            ClockServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
