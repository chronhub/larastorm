<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use RuntimeException;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Larastorm\Tests\Stubs\InvalidEventStore;
use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Larastorm\EventStore\EventStoreConnection;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Larastorm\Tests\Stubs\EventStoreConnectionStub;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

#[CoversClass(EventStoreConnection::class)]
class EventStoreConnectionTest extends UnitTestCase
{
    private MockObject|ChroniclerDB $chronicler;

    private Stream $stream;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(ChroniclerDB::class);
        $this->stream = new Stream(new StreamName('customer'));
    }

    #[Test]
    public function it_raise_exception_with_event_store_decorator_given(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicle given can not be a decorator:');

        new EventStoreConnectionStub($this->createMock(InvalidEventStore::class));
    }

    #[Test]
    public function it_create_stream(): void
    {
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->firstCommit($this->stream);
    }

    #[Test]
    public function it_update_stream(): void
    {
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->amend($this->stream);
    }

    #[Test]
    public function it_delete_stream(): void
    {
        $this->chronicler->expects($this->once())->method('delete')->with($this->stream->name());

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->delete($this->stream->name());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideDirection')]
    #[Test]
    public function it_retrieve_all_stream_events(string $direction): void
    {
        $identity = $this->createMock(AggregateIdentity::class);

        $this->chronicler->expects($this->once())->method('retrieveAll')->with($this->stream->name(), $identity, $direction);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->retrieveAll($this->stream->name(), $identity, $direction);
    }

    #[Test]
    public function it_retrieve_filtered_stream_events(): void
    {
        $queryFilter = $this->createMock(QueryFilter::class);

        $this->chronicler->expects($this->once())->method('retrieveFiltered')->with($this->stream->name(), $queryFilter);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->retrieveFiltered($this->stream->name(), $queryFilter);
    }

    #[Test]
    public function it_filter_stream_names_ordered_by_name(): void
    {
        $barStream = new StreamName('bar');
        $fooStream = new StreamName('foo');
        $zooStream = new StreamName('zoo');

        $this->chronicler->expects($this->once())
            ->method('filterStreamNames')
            ->with($zooStream, $barStream, $fooStream)
            ->willReturn([$fooStream, $zooStream]);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals([$fooStream, $zooStream], $es->filterStreamNames($zooStream, $barStream, $fooStream));
    }

    #[Test]
    public function it_filter_categories(): void
    {
        $this->chronicler->expects($this->once())
            ->method('filterCategoryNames')
            ->with('transaction')
            ->willReturn(['add', 'subtract']);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals(['add', 'subtract'], $es->filterCategoryNames('transaction'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_check_stream_exists(bool $isStreamExists): void
    {
        $this->chronicler->expects($this->once())
            ->method('hasStream')
            ->with($this->stream->name())
            ->willReturn($isStreamExists);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals($isStreamExists, $es->hasStream($this->stream->name()));
    }

    #[Test]
    public function it_return_event_stream_provider(): void
    {
        $provider = $this->createMock(EventStreamProvider::class);

        $this->chronicler->expects($this->once())
            ->method('getEventStreamProvider')
            ->willReturn($provider);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertSame($provider, $es->getEventStreamProvider());
    }

    #[Test]
    public function it_return_inner_chronicler(): void
    {
        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertSame($this->chronicler, $es->innerChronicler());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_check_if_persistence_is_during_creation_of_stream(bool $isCreation): void
    {
        $this->chronicler->expects($this->once())
            ->method('isDuringCreation')
            ->willReturn($isCreation);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals($isCreation, $es->isDuringCreation());
    }

    #[Test]
    public function it_raise_exception_during_creation(): void
    {
        $exception = new QueryException('some_connection_name', 'some sql', [], new RuntimeException('foo'));

        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($exception);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->firstCommit($this->stream);

        $this->assertSame($exception, $es->getRaisedException());
    }

    #[Test]
    public function it_raise_exception_during_update(): void
    {
        $exception = new QueryException('some_connection_name', 'some sql', [], new RuntimeException('foo'));

        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($exception);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->amend($this->stream);

        $this->assertSame($exception, $es->getRaisedException());
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
}
