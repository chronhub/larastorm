<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Chronicler\TrackStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\EventStore\MysqlEventStore;
use Chronhub\Storm\Stream\DetermineStreamCategory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Larastorm\EventStore\MysqlTransactionalEventStore;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\Persistence\MysqlSingleStreamPersistence;

#[CoversClass(EventStoreManager::class)]
#[CoversClass(EventStoreConnectionFactory::class)]
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

    #[Test]
    public function it_return_transactional_eventable_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publish', [
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'write_lock' => true,
            'strategy' => MysqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publish');

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(MysqlTransactionalEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $concreteEventStore);

        /** @var Connection $connection */
        $connection = ReflectionProperty::getProperty($concreteEventStore, 'connection');
        $this->assertEquals('mysql', $connection->getDriverName());

        $writeLock = ReflectionProperty::getProperty($concreteEventStore, 'writeLock');
        $this->assertEquals(MysqlWriteLock::class, $writeLock::class);

        $streamPersistence = ReflectionProperty::getProperty($concreteEventStore, 'streamPersistence');
        $this->assertEquals(MysqlSingleStreamPersistence::class, $streamPersistence::class);

        $eventLoader = ReflectionProperty::getProperty($concreteEventStore, 'eventLoader');
        $this->assertEquals(CursorQueryLoader::class, $eventLoader::class);

        $eventStreamProvider = ReflectionProperty::getProperty($concreteEventStore, 'eventStreamProvider');
        $this->assertInstanceOf(EventStreamProvider::class, $eventStreamProvider);

        $streamCategory = ReflectionProperty::getProperty($concreteEventStore, 'streamCategory');
        $this->assertInstanceOf(DetermineStreamCategory::class, $streamCategory);
    }

    #[Test]
    public function it_return_eventable_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publish', [
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackStream::class,
            ],
            'strategy' => MysqlSingleStreamPersistence::class,
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publish');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(EventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(MysqlEventStore::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(EventStoreDatabase::class, $concreteEventStore);
    }

    #[Test]
    public function it_return_standalone_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publish', [
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => null,
            ],
            'strategy' => MysqlSingleStreamPersistence::class,
            'is_transactional' => false,
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publish');

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(MysqlEventStore::class, $eventStore::class);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(EventStoreDatabase::class, $eventStore->innerChronicler()::class);
    }

    #[Test]
    public function it_return_transactional_standalone_instance(): void
    {
        $this->app['config']->set('chronicler.providers.connection.publish', [
            'store' => 'mysql',
            'strategy' => MysqlSingleStreamPersistence::class,
            'is_transactional' => true,
        ]);

        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        $eventStore = $this->manager->create('publish');

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);

        $this->assertEquals(MysqlTransactionalEventStore::class, $eventStore::class);
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
