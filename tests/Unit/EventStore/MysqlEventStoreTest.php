<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\MysqlEventStore;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Larastorm\Support\Contracts\ChroniclerConnection;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;

#[CoversClass(MysqlEventStore::class)]
final class MysqlEventStoreTest extends UnitTestCase
{
    private MockObject|ChroniclerConnection $chronicler;

    private Stream $stream;

    /**
     * @throws Exception
     */
    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(ChroniclerConnection::class);
        $this->stream = new Stream(new StreamName('customer'));
    }

    #[Test]
    public function it_raise_stream_already_exists_during_creation(): void
    {
        $this->expectException(StreamAlreadyExists::class);

        $queryException = QueryExceptionStub::withCode('23000');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(true);
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->firstCommit($this->stream);
    }

    #[DataProvider('provideAnyOtherCodeThanStreamExists')]
    #[Test]
    public function it_raise_query_failure_during_creation_on_any_other_error_code(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(true);
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->firstCommit($this->stream);
    }

    #[Test]
    public function it_raise_stream_not_found_during_update(): void
    {
        $this->expectException(StreamNotFound::class);

        $queryException = QueryExceptionStub::withCode('42S02');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    #[Test]
    public function it_raise_concurrency_exception_during_update(): void
    {
        $this->expectException(ConnectionConcurrencyException::class);

        $queryException = QueryExceptionStub::withCode('23000');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    #[DataProvider('provideAnyOtherCodeThanStreamExists')]
    #[Test]
    public function it_raise_query_failure_during_update_on_any_other_error_code(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    public static function provideAnyOtherCodeThanStreamExists(): Generator
    {
        yield ['40000'];
        yield ['00000'];
    }
}
