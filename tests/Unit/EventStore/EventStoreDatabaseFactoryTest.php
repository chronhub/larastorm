<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Larastorm\EventStore\WriteLock\LockFactory;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Larastorm\EventStore\Loader\EventLoaderConnectionFactory;
use Chronhub\Larastorm\EventStore\Persistence\EventStreamProviderFactory;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;

#[CoversClass(EventStoreDatabaseFactory::class)]
final class EventStoreDatabaseFactoryTest extends UnitTestCase
{
    private Container $container;

    private LockFactory|MockObject $lockFactory;

    private EventLoaderConnectionFactory|MockObject $streamEventLoaderFactory;

    private EventStreamProviderFactory|MockObject $eventStreamProviderFactory;

    private Connection|MockObject $connection;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::getInstance();
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->streamEventLoaderFactory = $this->createMock(EventLoaderConnectionFactory::class);
        $this->eventStreamProviderFactory = $this->createMock(EventStreamProviderFactory::class);
        $this->connection = $this->createMock(Connection::class);
    }

    #[DataProvider('provideBoolean')]
    public function testReturnEventStoreDatabase(bool $isTransactional): void
    {
        $config = [
            'strategy' => 'stream.persistence.pgsql',
            'query_loader' => 'cursor',
            'write_lock' => false,
            'event_stream_provider' => null,
        ];

        $writeLock = $this->createMock(WriteLockStrategy::class);
        $eventLoader = $this->createMock(StreamEventLoaderConnection::class);
        $persistence = $this->createMock(StreamPersistence::class);
        $streamCategory = $this->createMock(StreamCategory::class);
        $streamProvider = $this->createMock(EventStreamProvider::class);

        $this->lockFactory
            ->expects($this->once())
            ->method('createLock')
            ->with($this->connection, false)
            ->willReturn($writeLock);

        $this->streamEventLoaderFactory
            ->expects($this->once())
            ->method('createEventLoader')
            ->with('cursor')
            ->willReturn($eventLoader);

        $this->eventStreamProviderFactory
            ->expects($this->once())
            ->method('createProvider')
            ->with($this->connection, null)
            ->willReturn($streamProvider);

        $this->container->instance('stream.persistence.pgsql', $persistence);
        $this->container->instance(StreamCategory::class, $streamCategory);

        $this->assertFalse($this->container->bound(EventStreamProvider::class));

        $factory = new EventStoreDatabaseFactory(
            $this->lockFactory,
            $this->streamEventLoaderFactory,
            $this->eventStreamProviderFactory,
        );

        $factory->setContainer($this->container);

        $instance = $factory->createStore($this->connection, $isTransactional, $config);

        if ($isTransactional) {
            $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(EventStoreDatabase::class, $instance);
        }

        $streamPersistence = ReflectionProperty::getProperty($instance, 'streamPersistence');
        $this->assertSame($persistence, $streamPersistence);

        $this->assertSame($writeLock, ReflectionProperty::getProperty($instance, 'writeLock'));
        $this->assertSame($eventLoader, ReflectionProperty::getProperty($instance, 'eventLoader'));
        $this->assertSame($persistence, ReflectionProperty::getProperty($instance, 'streamPersistence'));
        $this->assertSame($streamCategory, ReflectionProperty::getProperty($instance, 'streamCategory'));
        $this->assertInstanceOf(EventStreamProvider::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
