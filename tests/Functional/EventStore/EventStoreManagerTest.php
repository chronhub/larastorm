<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\EventStore;

use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Larastorm\EventStore\EventStoreManager;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Larastorm\EventStore\Persistence\PerAggregateStreamPersistence;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerManager;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Storm\Contracts\Chronicler\StreamSubscriber;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Chronhub\Storm\Contracts\Tracker\Listener;
use Generator;
use Illuminate\Container\EntryNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(EventStoreManager::class)]
#[CoversClass(EventStoreConnectionFactory::class)]
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
    public function testCreateTransactionalEventableStore(string $storeDriver): void
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
    public function testCreateEventableStore(string $storeDriver): void
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
    public function testSubscribeToStore(string $storeDriver): void
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
    public function testCreateStandaloneInstance(string $storeDriver): void
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
    public function testCreateTransactionalStandaloneStore(string $storeDriver): void
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
    public function testExceptionRaisedWithMissingTransactionalKeyConfigToCreateStandaloneStore(string $storeDriver): void
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
    public function testResolveTrackerThroughIoc(string $storeDriver): void
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
    public function testExceptionRaisedWhenTrackerIdNotFoundInIoc(string $storeDriver): void
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
    public function testCreateFakeLockForPgsql(bool|string $writeLockForConfig): void
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
    public function testCreateFakeLockForMysql(bool|string $writeLockForConfig): void
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
    public function testCreateFakeLockWhenLockConfigIsNotDefined(string $storeDriver): void
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
    public function testCreateFakeLockWhenLockConfigIsNull(string $storeDriver): void
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
    public function testCreatePerAggregateStreamPersistence(string $storeDriver): void
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
    public function testCreateCursorQueryLoaderWhenLoaderConfigIsNotDefined(string $storeDriver): void
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
    public function testCreateLazyQueryLoaderWithDefaultChunk(string $storeDriver): void
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
    public function testCreateLazyQueryLoaderWithDefinedChunk(string $storeDriver): void
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

    public function testExceptionRaisedWhenStoreConfigIsNotSupported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection publish name with factory mongo is not defined');

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
        $this->manager->shouldUse('connection', EventStoreConnectionFactory::class);

        return $this->manager->create('publish');
    }

    private function setEventStoreConfig(array $config): void
    {
        $this->app['config']->set($this->configPath, $config);
    }
}
