<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Database\EventStoreDatabase;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Larastorm\Tests\Stubs\EventStoreStub;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(EventStoreDatabase::class)]
trait ProvideTestingStore
{
    public function testInstance(): void
    {
        $es = $this->eventStore();

        $this->assertFalse($es->isDuringCreation());
        $this->assertSame($this->eventStreamProvider, $es->getEventStreamProvider());
    }

    public function testSerializeStreamEvents(): void
    {
        $streamsEvents = [
            SomeEvent::fromContent(['foo' => 'bar']),
            SomeEvent::fromContent(['foo' => 'bar']),
        ];

        $eventAsArray = [
            'headers' => [
                Header::EVENT_TYPE => SomeEvent::class,
            ],
            'content' => ['foo' => 'bar'],
        ];

        $this->streamPersistence->expects($this->exactly(2))
            ->method('serialize')
            ->willReturnMap([
                [$streamsEvents[0], $eventAsArray],
                [$streamsEvents[1], $eventAsArray],
            ]);

        $events = $this->eventStore()->getStreamEventsSerialized($streamsEvents);

        $this->assertEquals([$eventAsArray, $eventAsArray], $events);
    }

    public static function provideDirection(): Generator
    {
        yield ['asc'];
        yield ['desc'];
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }

    protected function provideStreamEvents(): Generator
    {
        $i = 1;

        while ($i !== 5) {
            yield SomeEvent::fromContent(['count' => $i]);
            $i++;
        }

        return 4;
    }

    protected function eventStore(?WriteLockStrategy $writeLock = null, ?StreamPersistence $streamPersistence = null): EventStoreStub
    {
        return new EventStoreStub(
            $this->connection,
            $streamPersistence ?? $this->streamPersistence,
            $this->eventLoader,
            $this->eventStreamProvider,
            $this->streamCategory,
            $writeLock ?? $this->writeLock,
        );
    }

    protected Connection|ConnectionInterface|MockObject $connection;

    protected StreamPersistence|MockObject $streamPersistence;

    protected StreamEventLoaderConnection|MockObject $eventLoader;

    protected EventStreamProvider|MockObject $eventStreamProvider;

    protected StreamCategory|MockObject $streamCategory;

    protected WriteLockStrategy|MockObject $writeLock;

    protected StreamName $streamName;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->streamPersistence = $this->createMock(StreamPersistence::class);
        $this->eventLoader = $this->createMock(StreamEventLoaderConnection::class);
        $this->eventStreamProvider = $this->createMock(EventStreamProvider::class);
        $this->streamCategory = $this->createMock(StreamCategory::class);
        $this->writeLock = $this->createMock(WriteLockStrategy::class);
        $this->streamName = new StreamName('customer');
    }
}
