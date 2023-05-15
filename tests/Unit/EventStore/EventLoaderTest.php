<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Chronicler\Exceptions\NoStreamEventReturn;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use stdClass;

#[CoversClass(EventLoader::class)]
final class EventLoaderTest extends UnitTestCase
{
    private StreamEventSerializer|MockObject $serializer;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(StreamEventSerializer::class);
        $this->streamName = new StreamName('customer');
    }

    public function testGenerateDomainEvents(): void
    {
        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'steph bug'];
        $event->no = 5;

        $streamEvents = new Collection([$event]);

        $expectedEvent = SomeEvent::fromContent(['name' => 'steph bug'])->withHeader('some', 'header');

        $this->serializer->expects($this->once())
            ->method('deserializePayload')
            ->with((array) $event)
            ->willReturn($expectedEvent);

        $eventLoader = new EventLoader($this->serializer);

        $generator = $eventLoader($streamEvents, $this->streamName);

        $this->assertEquals($generator->current(), $expectedEvent);

        $generator->next();

        $this->assertEquals(1, $generator->getReturn());
    }

    public function testExceptionRaisedWhenGeneratedEventIsEmpty(): void
    {
        $this->expectException(NoStreamEventReturn::class);

        $this->serializer->expects($this->never())->method('deserializePayload');

        $eventLoader = new EventLoader($this->serializer);

        $eventLoader(new Collection([]), $this->streamName)->current();
    }

    #[Test]
    public function testExceptionRaisedWhenStreamNotFound(): void
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
            ->method('deserializePayload')
            ->with((array) $event)
            ->willReturn($expectedEvent);

        $this->serializer->expects($this->once())
            ->method('deserializePayload')
            ->with((array) $event)
            ->will($this->throwException($queryException));

        $eventLoader = new EventLoader($this->serializer);

        $eventLoader($streamEvents, $this->streamName)->current();
    }
}
