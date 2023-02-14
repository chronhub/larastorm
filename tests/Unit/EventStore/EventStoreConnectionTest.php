<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use RuntimeException;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Larastorm\Tests\Stubs\EventStoreConnectionStub;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;

class EventStoreConnectionTest extends ProphecyTestCase
{
    private ObjectProphecy|ChroniclerConnection $chronicler;

    private Stream $stream;

    protected function setUp(): void
    {
        $this->chronicler = $this->prophesize(ChroniclerConnection::class);
        $this->stream = new Stream(new StreamName('customer'));
    }

    /**
     * @test
     */
    public function it_create_stream(): void
    {
        $this->chronicler->firstCommit($this->stream)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->firstCommit($this->stream);
    }

    /**
     * @test
     */
    public function it_update_stream(): void
    {
        $this->chronicler->amend($this->stream)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->amend($this->stream);
    }

    /**
     * @test
     */
    public function it_delete_stream(): void
    {
        $this->chronicler->delete($this->stream->name())->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->delete($this->stream->name());
    }

    /**
     * @test
     *
     * @dataProvider provideDirection
     */
    public function it_retrieve_all_stream_events(string $direction): void
    {
        $identity = $this->prophesize(AggregateIdentity::class)->reveal();

        $this->chronicler->retrieveAll($this->stream->name(), $identity, $direction)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->retrieveAll($this->stream->name(), $identity, $direction);
    }

    /**
     * @test
     */
    public function it_retrieve_filtered_stream_events(): void
    {
        $queryFilter = $this->prophesize(QueryFilter::class)->reveal();

        $this->chronicler->retrieveFiltered($this->stream->name(), $queryFilter)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->retrieveFiltered($this->stream->name(), $queryFilter);
    }

    /**
     * @test
     */
    public function it_filter_stream_names_ordered_by_name(): void
    {
        $barStream = new StreamName('bar');
        $fooStream = new StreamName('foo');
        $zooStream = new StreamName('zoo');

        $this->chronicler->filterStreamNames($zooStream, $barStream, $fooStream)->willReturn([$fooStream, $zooStream])->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $this->assertEquals([$fooStream, $zooStream], $es->filterStreamNames($zooStream, $barStream, $fooStream));
    }

    /**
     * @test
     */
    public function it_filter_categories(): void
    {
        $this->chronicler->filterCategoryNames('transaction')->willReturn(['add', 'subtract'])->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $this->assertEquals(['add', 'subtract'], $es->filterCategoryNames('transaction'));
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_check_stream_exists(bool $isStreamExists): void
    {
        $this->chronicler->hasStream($this->stream->name())->willReturn($isStreamExists)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $this->assertEquals($isStreamExists, $es->hasStream($this->stream->name()));
    }

    /**
     * @test
     */
    public function it_return_event_stream_provider(): void
    {
        $provider = $this->prophesize(EventStreamProvider::class)->reveal();

        $this->chronicler->getEventStreamProvider()->willReturn($provider)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $this->assertSame($provider, $es->getEventStreamProvider());
    }

    /**
     * @test
     */
    public function it_return_inner_chronicler(): void
    {
        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $this->assertSame($this->chronicler->reveal(), $es->innerChronicler());
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_check_if_persistence_is_during_creation_of_stream(bool $isCreation): void
    {
        $this->chronicler->isDuringCreation()->willReturn($isCreation)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $this->assertEquals($isCreation, $es->isDuringCreation());
    }

    /**
     * @test
     */
    public function it_raise_exception_during_creation(): void
    {
        $exception = new QueryException('some_connection_name', 'some sql', [], new RuntimeException('foo'));

        $this->chronicler->firstCommit($this->stream)->willThrow($exception)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->firstCommit($this->stream);

        $this->assertSame($exception, $es->getRaisedException());
    }

    /**
     * @test
     */
    public function it_raise_exception_during_update(): void
    {
        $exception = new QueryException('some_connection_name', 'some sql', [], new RuntimeException('foo'));

        $this->chronicler->amend($this->stream)->willThrow($exception)->shouldBeCalledOnce();

        $es = new EventStoreConnectionStub($this->chronicler->reveal());

        $es->amend($this->stream);

        $this->assertSame($exception, $es->getRaisedException());
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
}
