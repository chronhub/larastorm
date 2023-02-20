<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Illuminate\Database\Connection;
use Chronhub\Storm\Stream\StreamName;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Storm\Contracts\Message\Header;
use Illuminate\Database\ConnectionInterface;
use Chronhub\Larastorm\Tests\Stubs\StoreStub;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoaderConnection;

trait ProvideTestingStore
{
    /**
     * @test
     */
    public function it_can_be_constructed(): void
    {
        $es = $this->eventStore();

        $this->assertFalse($es->isDuringCreation());
        $this->assertSame($this->eventStreamProvider->reveal(), $es->getEventStreamProvider());
    }

    /**
     * @test
     */
    public function it_assert_serialized_stream_events(): void
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

        $this->streamPersistence->serialize($streamsEvents[0])->willReturn($eventAsArray)->shouldBeCalledTimes(2);
        $this->streamPersistence->serialize($streamsEvents[1])->willReturn($eventAsArray)->shouldBeCalledTimes(2);

        $events = $this->eventStore()->getStreamEventsSerialized($streamsEvents);

        $this->assertEquals([$eventAsArray, $eventAsArray], $events);
    }

    public function provideDirection(): Generator
    {
        yield ['asc'];
        yield ['desc'];
    }

    public function provideBoolean(): Generator
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

    protected function eventStore(?WriteLockStrategy $writeLock = null): StoreStub
    {
        return new StoreStub(
            $this->connection->reveal(),
            $this->streamPersistence->reveal(),
            $this->eventLoader->reveal(),
            $this->eventStreamProvider->reveal(),
            $this->streamCategory->reveal(),
            $writeLock ?? $this->writeLock->reveal(),
        );
    }

    protected Connection|ConnectionInterface|ObjectProphecy $connection;

    protected StreamPersistence|ObjectProphecy $streamPersistence;

    protected StreamEventLoaderConnection|ObjectProphecy $eventLoader;

    protected EventStreamProvider|ObjectProphecy $eventStreamProvider;

    protected StreamCategory|ObjectProphecy $streamCategory;

    protected WriteLockStrategy|ObjectProphecy $writeLock;

    protected StreamName $streamName;

    protected function setUp(): void
    {
        $this->connection = $this->prophesize(Connection::class);
        $this->streamPersistence = $this->prophesize(StreamPersistence::class);
        $this->eventLoader = $this->prophesize(StreamEventLoaderConnection::class);
        $this->eventStreamProvider = $this->prophesize(EventStreamProvider::class);
        $this->streamCategory = $this->prophesize(StreamCategory::class);
        $this->writeLock = $this->prophesize(WriteLockStrategy::class);
        $this->streamName = new StreamName('customer');
    }
}
