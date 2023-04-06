<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\EventStoreConnection;
use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Larastorm\Tests\Stubs\DummyDecoratorDatabaseEventStore;
use Chronhub\Larastorm\Tests\Stubs\EventStoreConnectionStub;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

#[CoversClass(EventStoreConnection::class)]
class EventStoreConnectionTest extends UnitTestCase
{
    private MockObject|ChroniclerDB $chronicler;

    private Stream $stream;

    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(ChroniclerDB::class);
        $this->stream = new Stream(new StreamName('customer'));
    }

    #[Test]
    public function testExceptionRaisedWhenEventStoreIsAlreadyDecorated(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chronicle given can not be a decorator:');

        new EventStoreConnectionStub($this->createMock(DummyDecoratorDatabaseEventStore::class));
    }

    public function testFirstCommit(): void
    {
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->firstCommit($this->stream);
    }

    public function testAmendStream(): void
    {
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->amend($this->stream);
    }

    public function testDeleteStream(): void
    {
        $this->chronicler->expects($this->once())->method('delete')->with($this->stream->name());

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->delete($this->stream->name());
    }

    #[DataProvider('provideDirection')]
    public function testRetrieveAllSortedStreamEvents(string $direction): void
    {
        $identity = $this->createMock(AggregateIdentity::class);

        $this->chronicler->expects($this->once())->method('retrieveAll')->with($this->stream->name(), $identity, $direction);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->retrieveAll($this->stream->name(), $identity, $direction);
    }

    public function testRetrieveFilteredStreamEvents(): void
    {
        $queryFilter = $this->createMock(QueryFilter::class);

        $this->chronicler->expects($this->once())->method('retrieveFiltered')->with($this->stream->name(), $queryFilter);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->retrieveFiltered($this->stream->name(), $queryFilter);
    }

    public function testFilterStreamNamesSortedByAscendantNames(): void
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

    public function testFilterCategories(): void
    {
        $this->chronicler->expects($this->once())
            ->method('filterCategoryNames')
            ->with('transaction')
            ->willReturn(['add', 'subtract']);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals(['add', 'subtract'], $es->filterCategoryNames('transaction'));
    }

    #[DataProvider('provideBoolean')]
    public function testCheckStreamExists(bool $isStreamExists): void
    {
        $this->chronicler->expects($this->once())
            ->method('hasStream')
            ->with($this->stream->name())
            ->willReturn($isStreamExists);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals($isStreamExists, $es->hasStream($this->stream->name()));
    }

    public function testGetEventStreamProviderInstance(): void
    {
        $provider = $this->createMock(EventStreamProvider::class);

        $this->chronicler->expects($this->once())
            ->method('getEventStreamProvider')
            ->willReturn($provider);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertSame($provider, $es->getEventStreamProvider());
    }

    public function testGetUnderlyingEventStore(): void
    {
        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertSame($this->chronicler, $es->innerChronicler());
    }

    #[DataProvider('provideBoolean')]
    public function testCheckIfStreamPersistenceIsDuringCreation(bool $isCreation): void
    {
        $this->chronicler->expects($this->once())
            ->method('isDuringCreation')
            ->willReturn($isCreation);

        $es = new EventStoreConnectionStub($this->chronicler);

        $this->assertEquals($isCreation, $es->isDuringCreation());
    }

    public function testExceptionRaisedDuringCreation(): void
    {
        $exception = new QueryException('some_connection_name', 'some sql', [], new RuntimeException('foo'));

        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($exception);

        $es = new EventStoreConnectionStub($this->chronicler);

        $es->firstCommit($this->stream);

        $this->assertSame($exception, $es->getRaisedException());
    }

    public function testExceptionRaisedDuringAmend(): void
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
