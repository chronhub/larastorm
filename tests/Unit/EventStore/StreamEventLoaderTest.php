<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use stdClass;
use Illuminate\Support\Collection;
use Chronhub\Storm\Stream\StreamName;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Larastorm\EventStore\Loader\StreamEventLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

final class StreamEventLoaderTest extends ProphecyTestCase
{
    private StreamEventSerializer|ObjectProphecy $serializer;

    private StreamName $streamName;

    protected function setUp(): void
    {
        $this->serializer = $this->prophesize(StreamEventSerializer::class);
        $this->streamName = new StreamName('customer');
    }

    /**
     * @test
     */
    public function it_yield_domain_events(): void
    {
        $event = new stdClass();
        $event->headers = ['some' => 'header'];
        $event->content = ['name' => 'steph bug'];
        $event->no = 5;

        $streamEvents = new Collection([$event]);

        $expectedEvent = SomeEvent::fromContent(['name' => 'steph bug'])->withHeader('some', 'header');
        $this->serializer->unserializeContent((array) $event)->willYield([$expectedEvent])->shouldBeCalledOnce();

        $eventLoader = new StreamEventLoader($this->serializer->reveal());

        $generator = $eventLoader($streamEvents, $this->streamName);

        $this->assertEquals($generator->current(), $expectedEvent);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_no_stream_event_has_been_yield(): void
    {
        $this->expectException(StreamNotFound::class);

        $this->serializer->unserializeContent([])->willYield([])->shouldNotBeCalled();

        $eventLoader = new StreamEventLoader($this->serializer->reveal());

        $eventLoader(new Collection([]), $this->streamName)->current();
    }

    /**
     * @test
     */
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

        $this->serializer->unserializeContent((array) $event)->willYield([$expectedEvent])->shouldBeCalledTimes(2);

        $this->serializer->unserializeContent((array) $event)
            ->willThrow($queryException)
            ->shouldBeCalledOnce();

        $eventLoader = new StreamEventLoader($this->serializer->reveal());

        $eventLoader($streamEvents, $this->streamName)->current();
    }

    /**
     * @test
     */
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

        $this->serializer->unserializeContent((array) $event)->willReturn($expectedEvent)->shouldBeCalledTimes(2);
        $this->serializer->unserializeContent((array) $event)->willThrow($queryException)->shouldBeCalledOnce();

        $eventLoader = new StreamEventLoader($this->serializer->reveal());

        $eventLoader($streamEvents, $this->streamName)->current();
    }
}
