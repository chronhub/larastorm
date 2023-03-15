<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Chronicler\TrackStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Storm\Contracts\Tracker\Listener;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Container\EntryNotFoundException;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Storm\Contracts\Chronicler\StreamSubscriber;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\EventStoreConnectionProvider;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;
use Chronhub\Larastorm\EventStore\Persistence\PerAggregateStreamPersistence;

#[CoversClass(EventStoreManager::class)]
#[CoversClass(EventStoreConnectionProvider::class)]
final class EventStoreManagerTest extends OrchestraTestCase
{
    private EventStoreManager $manager;

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app[ChroniclerManager::class];

        $this->assertEquals('connection', $this->manager->getDefaultDriver());
        $this->assertEquals('connection', config('chronicler.defaults.provider'));
        $this->configPath = 'chronicler.providers.connection.publish';
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_transactional_eventable_instance(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_eventable_instance(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertEquals(EventChronicler::class, $eventStore::class);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(ChroniclerDecorator::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();
        $this->assertInstanceOf(EventStoreDatabase::class, $concreteEventStore);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_subscribe_to_eventable_instance(string $storeDriver): void
    {
        $noOpStreamSubscriber = new class() implements StreamSubscriber
        {
            public function attachToChronicler(EventableChronicler $chronicler): void
            {
                $chronicler->subscribe('foo', function (): void {
                    //
                }, -99999);
            }

            public function detachFromChronicler(EventableChronicler $chronicler): void
            {
                //
            }
        };

        $this->app->instance('stream_subscriber.no_op', $noOpStreamSubscriber);

        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [
                    $noOpStreamSubscriber,
                    'stream_subscriber.no_op',
                ],
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(EventChronicler::class, $eventStore);

        $streamTracker = ReflectionProperty::getProperty($eventStore, 'tracker');
        $this->assertInstanceOf(TrackStream::class, $streamTracker);

        $this->assertCount(
            2,
            $streamTracker
                ->listeners()
                ->filter(fn (Listener $listener): bool => $listener->name() === 'foo')
        );
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_standalone_instance(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => null,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
            'is_transactional' => false,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertNotInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(EventStoreDatabase::class, $eventStore->innerChronicler()::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_transactional_standalone_instance(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => null,
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
            'is_transactional' => true,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertNotInstanceOf(EventableChronicler::class, $eventStore);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);
        $this->assertEquals(EventStoreTransactionalDatabase::class, $eventStore->innerChronicler()::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_raise_exception_when_missing_is_transactional_key_in_config_to_create_standalone_instance(string $storeDriver): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config key is_transactional is required when no stream tracker is provided for chronicler name publish');

        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
            'write_lock' => true,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $this->assertNull(config('chronicler.providers.connection.write.is_transactional'));

        $this->createEventStoreInstance();
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_instance_with_tracker_id_resolved_from_container(string $storeDriver): void
    {
        $trackerInstance = new TrackTransactionalStream();

        $this->app->instance('event_store.tracker.id', $trackerInstance);

        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => 'event_store.tracker.id',
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(TransactionalChronicler::class, $eventStore);
        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);

        $tracker = ReflectionProperty::getProperty($eventStore, 'tracker');
        $this->assertSame($trackerInstance, $tracker);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_raise_exception_when_tracker_id_can_not_be_resolved_from_container(string $storeDriver): void
    {
        $this->expectException(EntryNotFoundException::class);

        $this->assertFalse($this->app->bound('some_tracker_instance'));

        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => 'some_tracker_instance',
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $this->createEventStoreInstance();
    }

    #[DataProvider('provideFakeWriteLockForConfig')]
    #[Test]
    public function it_create_instance_with_fake_lock_for_pgsql(bool|string $writeLockForConfig): void
    {
        $this->setEventStoreConfig([
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
            'write_lock' => $writeLockForConfig,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(ChroniclerDecorator::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();

        $writeLock = ReflectionProperty::getProperty($concreteEventStore, 'writeLock');
        $this->assertEquals(FakeWriteLock::class, $writeLock::class);
    }

    #[DataProvider('provideFakeWriteLockForConfig')]
    #[Test]
    public function it_return_instance_with_fake_lock_for_mysql(bool|string $writeLockForConfig): void
    {
        $this->setEventStoreConfig([
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
                'subscribers' => [],
            ],
            'write_lock' => $writeLockForConfig,
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'cursor',
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(ChroniclerDecorator::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();

        $writeLock = ReflectionProperty::getProperty($concreteEventStore, 'writeLock');
        $this->assertEquals(FakeWriteLock::class, $writeLock::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_fake_lock_when_write_lock_is_not_defined(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $this->createEventStoreInstance();
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_fake_lock_when_write_lock_is_null(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'write_lock' => null,
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $this->createEventStoreInstance();
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_instance_with_per_aggregate_stream_persistence(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PerAggregateStreamPersistence::class,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(ChroniclerDecorator::class, $decoratorEventStore);
        $concreteEventStore = $decoratorEventStore->innerChronicler();

        $streamPersistence = ReflectionProperty::getProperty($concreteEventStore, 'streamPersistence');
        $this->assertEquals(PerAggregateStreamPersistence::class, $streamPersistence::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_instance_of_cursor_query_loader_when_query_loader_key_missing(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertInstanceOf(ChroniclerDecorator::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();

        $eventLoader = ReflectionProperty::getProperty($concreteEventStore, 'eventLoader');
        $this->assertEquals(CursorQueryLoader::class, $eventLoader::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_instance_with_lazy_query_loader(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'lazy',
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $decoratorEventStore = $eventStore->innerChronicler();
        $this->assertNotInstanceOf(EventableChronicler::class, $decoratorEventStore);
        $this->assertInstanceOf(ChroniclerDecorator::class, $decoratorEventStore);

        $concreteEventStore = $decoratorEventStore->innerChronicler();

        $eventLoader = ReflectionProperty::getProperty($concreteEventStore, 'eventLoader');
        $this->assertEquals(LazyQueryLoader::class, $eventLoader::class);
    }

    #[DataProvider('provideStoreDriver')]
    #[Test]
    public function it_create_instance_with_lazy_query_loader_and_defined_chunk_size(string $storeDriver): void
    {
        $this->setEventStoreConfig([
            'store' => $storeDriver,
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
            'query_loader' => 'lazy:10',
        ]);

        $eventStore = $this->createEventStoreInstance();

        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        while ($eventStore instanceof ChroniclerDecorator) {
            $eventStore = $eventStore->innerChronicler();
        }

        $eventLoader = ReflectionProperty::getProperty($eventStore, 'eventLoader');
        $this->assertInstanceOf(LazyQueryLoader::class, $eventLoader);
        $this->assertEquals(10, $eventLoader->chunkSize);
    }

    #[Test]
    public function it_raise_exception_when_config_store_is_not_supported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection publish with provider mongo is not defined');

        $this->setEventStoreConfig([
            'store' => 'mongo',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
            ],
            'strategy' => PgsqlSingleStreamPersistence::class,
        ]);

        $this->createEventStoreInstance();
    }

    public static function provideFakeWriteLockForConfig(): Generator
    {
        yield [false];
        yield [FakeWriteLock::class];
    }

    public static function provideStoreDriver(): Generator
    {
        yield ['mysql'];
        yield ['pgsql'];
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }

    private function createEventStoreInstance(): Chronicler
    {
        $this->manager->shouldUse('connection', EventStoreConnectionProvider::class);

        return $this->manager->create('publish');
    }

    private function setEventStoreConfig(array $config): void
    {
        $this->app['config']->set($this->configPath, $config);
    }
}
