<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Chronicler\TrackStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Chronicler\EventChronicler;
use Chronhub\Storm\Contracts\Tracker\Listener;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\EventStore\MysqlEventStore;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\EventStore\EventStoreConnection;
use Chronhub\Storm\Chronicler\TrackTransactionalStream;
use Chronhub\Storm\Contracts\Chronicler\StreamSubscriber;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Storm\Chronicler\TransactionalEventChronicler;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerDecorator;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\EventStoreConnectionFactory;
use Chronhub\Larastorm\EventStore\MysqlTransactionalEventStore;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Storm\Contracts\Chronicler\TransactionalEventableChronicler;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\Persistence\MysqlSingleStreamPersistence;

#[CoversClass(EventStoreConnectionFactory::class)]
class MysqlEventStoreConnectionFactoryTest extends OrchestraTestCase
{
    #[Test]
    public function it_create_mysql_event_store(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'mysql',
            'is_transactional' => false,
            'tracking' => [
                'subscribers' => [],
            ],
        ]);

        $this->assertInstanceOf(MysqlEventStore::class, $eventStore);
        $this->assertEquals(EventStoreDatabase::class, $eventStore->innerChronicler()::class);
    }

    #[Test]
    public function it_create_mysql_transactional_event_store(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'mysql',
            'is_transactional' => true,
            'tracking' => [
                'subscribers' => [],
            ],
        ]);

        $this->assertInstanceOf(MysqlTransactionalEventStore::class, $eventStore);
        $this->assertEquals(EventStoreTransactionalDatabase::class, $eventStore->innerChronicler()::class);
    }

    #[Test]
    public function it_create_mysql_event_store_eventable(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackStream::class,
                'subscribers' => [],
            ],
        ]);

        $this->assertEquals(EventChronicler::class, $eventStore::class);
        $this->assertInstanceOf(EventableChronicler::class, $eventStore);

        $store = $eventStore->innerChronicler();
        $this->assertEquals(MysqlEventStore::class, $store::class);
        $this->assertInstanceOf(EventStoreConnection::class, $store);

        $database = $store->innerChronicler();
        $this->assertEquals(EventStoreDatabase::class, $database::class);
    }

    #[Test]
    public function it_create_mysql_transactional_event_store_eventable(): void
    {
        $provider = $this->newInstance();

        $eventStore = $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => TrackTransactionalStream::class,
                'subscribers' => [],
            ],
        ]);

        $this->assertEquals(TransactionalEventChronicler::class, $eventStore::class);
        $this->assertInstanceOf(TransactionalEventableChronicler::class, $eventStore);
        $this->assertInstanceOf(ChroniclerDecorator::class, $eventStore);

        $store = $eventStore->innerChronicler();
        $this->assertEquals(MysqlTransactionalEventStore::class, $store::class);
        $this->assertInstanceOf(EventStoreConnection::class, $store);

        $database = $store->innerChronicler();
        $this->assertEquals(EventStoreTransactionalDatabase::class, $database::class);
    }

    #[Test]
    public function it_attach_stream_subscribers_to_event_store_eventable(): void
    {
        $tracker = new TrackStream();
        $this->app->instance('tracker.stream.default', $tracker);

        $provider = $this->newInstance();

        $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'mysql',
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

    #[Test]
    public function it_raise_exception_when_tracker_and_is_transactional_is_not_defined(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config key is_transactional is required when no stream tracker is provided for chronicler name');

        $provider = $this->newInstance();

        $provider->createEventStore('default', [
            'strategy' => MysqlSingleStreamPersistence::class,
            'store' => 'mysql',
            'tracking' => [
                'tracker_id' => null,
                'subscribers' => [],
            ],
        ]);
    }

    #[Test]
    public function it_raise_exception_when_store_drive_is_not_supported(): void
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
