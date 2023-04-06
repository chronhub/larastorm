<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\EventStoreConnection;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Larastorm\EventStore\Persistence\MysqlSingleStreamPersistence;
use Chronhub\Larastorm\EventStore\Persistence\PgsqlSingleStreamPersistence;
use Chronhub\Larastorm\EventStore\PgsqlEventStore;
use Chronhub\Larastorm\EventStore\PgsqlTransactionalEventStore;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Chronicler\TrackStream;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Storm\Contracts\Chronicler\StreamSubscriber;
use Chronhub\Storm\Contracts\Chronicler\TransactionalEventableChronicler;
use Chronhub\Storm\Contracts\Tracker\Listener;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventStoreConnectionFactory::class)]
class PgsqlEventStoreConnectionFactoryTest extends OrchestraTestCase
{
    public function testPgsqlEventStore(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'pgsql',
            'is_transactional' => false,
            'tracking' => [
                'subscribers' => [],
            ],
        ]);

        $this->assertInstanceOf(PgsqlEventStore::class, $eventStore);
        $this->assertEquals(EventStoreDatabase::class, $eventStore->innerChronicler()::class);
    }

    public function testPgsqlTransactionalEventStore(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => PgsqlSingleStreamPersistence::class,
            'store' => 'pgsql',
            'is_transactional' => true,
            'tracking' => [
                'subscribers' => [],
            ],
        ]);

        $this->assertInstanceOf(PgsqlTransactionalEventStore::class, $eventStore);
        $this->assertEquals(EventStoreTransactionalDatabase::class, $eventStore->innerChronicler()::class);
    }

    public function testPgsqlEventableEventStore(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [],
            ],
        ]);

        $this->assertEquals(EventChronicler::class, $eventStore::class);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $store = $eventStore->innerChronicler();
        $this->assertEquals(PgsqlEventStore::class, $store::class);
        $this->assertInstanceOf(EventStoreConnection::class, $store);

        $database = $store->innerChronicler();
        $this->assertEquals(EventStoreDatabase::class, $database::class);
    }

    public function testPgsqlTransactionalEventableEventStore(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
                'subscribers' => [],
            ],
        ]);

        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);
        $this->assertInstanceOf(TransactionalEventableChronicler::class, $eventStore);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);

        $store = $eventStore->innerChronicler();
        $this->assertEquals(PgsqlTransactionalEventStore::class, $store::class);
        $this->assertInstanceOf(EventStoreConnection::class, $store);

        $database = $store->innerChronicler();
        $this->assertEquals(EventStoreTransactionalDatabase::class, $database::class);
    }

    public function testSubscribeToEventStore(): void
    {
        $tracker = new TrackStream();
        $this->app->instance('tracker.stream.default', $tracker);

        $provider = $this->newInstance();

        $provider->createEventStore('default', [
            'strategy' => PgsqlSingleStreamPersistence::class,
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => 'tracker.stream.default',
                'subscribers' => [
                    new class implements StreamSubscriber
                    {
                        public function attachToChronicler(EventableChronicler $chronicler): void
                        {
                            $chronicler->subscribe('foo_bar', fn () => null, 150);
                        }

                        public function detachFromChronicler(EventableChronicler $chronicler): void
                        {
                            //
                        }
                    },
                ],
            ],
        ]);

        $subscriber = $tracker->listeners()->skipUntil(fn (Listener $listener) => $listener->name() === 'foo_bar')->first();
        $this->assertInstanceOf(Listener::class, $subscriber);

        $this->assertEquals('foo_bar', $subscriber->name());
        $this->assertEquals(150, $subscriber->priority());
    }

    public function testExceptionRaisedWhenIsTransactionalMissingInConfig(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config key is_transactional is required when no stream tracker is provided for chronicler name');

        $provider = $this->newInstance();

        $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'pgsql',
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
        ]);
    }

    public function testExceptionRaisedWhenEventStoreFactoryIsNotDefined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Connection default name with factory foo is not defined');

        $provider = $this->newInstance();

        $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'foo',
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [],
            ],
        ]);
    }

    private function newInstance(): EventStoreConnectionFactory
    {
        return new EventStoreConnectionFactory(
            fn () => $this->app,
            $this->app[EventStoreDatabaseFactory::class]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            ChroniclerServiceProvider::class,
        ];
    }
}
