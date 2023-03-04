<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Illuminate\Support\Collection;
use Chronhub\Storm\Stream\StreamName;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

#[CoversClass(StreamEventLoader::class)]
final class StreamEventLoaderTest extends UnitTestCase
{
    private StreamEventSerializer|MockObject $serializer;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(StreamEventSerializer::class);
        $this->streamName = new StreamName('customer');
    }

    #[Test]
    public function it_yield_domain_events(): void
    {
        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'steph bug'];
        $event->no = 5;

        $streamEvents = new Collection([$event]);

        $expectedEvent = SomeEvent::fromContent(['name' => 'steph bug'])->withHeader('some', 'header');

        $this->serializer->expects($this->once())
            ->method('unserializeContent')
            ->with((array) $event)
            ->will($this->returnCallback(function () use ($expectedEvent) {
                yield $expectedEvent;
            }));

        $eventLoader = new EventLoader($this->serializer);

        $generator = $eventLoader($streamEvents, $this->streamName);

        $this->assertEquals($generator->current(), $expectedEvent);
    }

    #[Test]
    public function it_raise_exception_when_no_stream_event_has_been_yield(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->serializer->expects($this->never())->method('unserializeContent');

        $eventLoader = new EventLoader($this->serializer);

        $eventLoader(new Collection([]), $this->streamName)->current();
    }

    #[Test]
    public function it_raise_exception_when_stream_name_not_found_in_database(): void
    {
        $this->expectException(StreamNotFound::class);

        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'steph bug'];
        $event->no = 5;

        $streamEvents = new Collection([$event, $event]);

        $queryException = QueryExceptionStub::withCode('1234');

        $expectedEvent = SomeEvent::fromContent(['name' => 'steph bug'])->withHeader('some', 'header');

        $this->serializer->expects($this->once())
            ->method('unserializeContent')
            ->with((array) $event)
            ->will($this->returnCallback(function () use ($expectedEvent) {
                yield $expectedEvent;
            }));

        $this->serializer->expects($this->once())
            ->method('unserializeContent')
            ->with((array) $event)
            ->will($this->throwException($queryException));

        $eventLoader = new EventLoader($this->serializer);

        $eventLoader($streamEvents, $this->streamName)->current();
    }

    #[Test]
    public function it_raise_exception_on_query_exception_when_no_row_has_been_affected(): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'steph bug'];
        $event->no = 5;

        $streamEvents = new Collection([$event, $event]);

        $queryException = QueryExceptionStub::withCode('00000');

        $expectedEvent = SomeEvent::fromContent(['name' => 'steph bug'])->withHeader('some', 'header');

        $this->serializer->expects($this->once())
            ->method('unserializeContent')
            ->with((array) $event)
            ->will($this->returnCallback(function () use ($expectedEvent) {
                yield $expectedEvent;
            }));

        $this->serializer->expects($this->once())
            ->method('unserializeContent')
            ->with((array) $event)
            ->will($this->throwException($queryException));

        $eventLoader = new EventLoader($this->serializer);

        $eventLoader($streamEvents, $this->streamName)->current();
    }
}
