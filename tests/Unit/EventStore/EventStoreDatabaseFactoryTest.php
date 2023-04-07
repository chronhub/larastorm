<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\EventStore\Database\EventStoreDatabaseFactory;
use Chronhub\Larastorm\EventStore\Database\EventStoreTransactionalDatabase;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\WriteLock\FakeWriteLock;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Tests\Util\ReflectionProperty;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(EventStoreDatabaseFactory::class)]
final class EventStoreDatabaseFactoryTest extends UnitTestCase
{
    private Container $container;

    private Connection|MockObject $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = Container::getInstance();
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

        $persistence = $this->createMock(StreamPersistence::class);
        $streamCategory = $this->createMock(StreamCategory::class);

        $this->container->instance(StreamEventLoader::class, $this->createMock(StreamEventLoader::class));
        $this->container->instance('stream.persistence.pgsql', $persistence);
        $this->container->instance(StreamCategory::class, $streamCategory);

        $this->assertFalse($this->container->bound(EventStreamProvider::class));

        $factory = new EventStoreDatabaseFactory();
        $factory->setContainer($this->container);

        $instance = $factory->createStore($this->connection, $isTransactional, $config);

        if ($isTransactional) {
            $this->assertInstanceOf(EventStoreTransactionalDatabase::class, $instance);
        } else {
            $this->assertInstanceOf(EventStoreDatabase::class, $instance);
        }

        $streamPersistence = ReflectionProperty::getProperty($instance, 'streamPersistence');
        $this->assertSame($persistence, $streamPersistence);
        $this->assertSame($streamCategory, ReflectionProperty::getProperty($instance, 'streamCategory'));

        $this->assertInstanceOf(FakeWriteLock::class, ReflectionProperty::getProperty($instance, 'writeLock'));
        $this->assertInstanceOf(CursorQueryLoader::class, ReflectionProperty::getProperty($instance, 'eventLoader'));
        $this->assertInstanceOf(EventStreamProvider::class, ReflectionProperty::getProperty($instance, 'eventStreamProvider'));
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
