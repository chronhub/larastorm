<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\MysqlEventStore;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Larastorm\Support\Contracts\ChroniclerConnection;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;

#[CoversClass(MysqlEventStore::class)]
final class MysqlEventStoreTest extends UnitTestCase
{
    private MockObject|ChroniclerConnection $chronicler;

    private Stream $stream;

    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(ChroniclerConnection::class);
        $this->stream = new Stream(new StreamName('customer'));
    }

    public function testRaiseStreamAlreadyExistsOnCreation(): void
    {
        $this->expectException(StreamAlreadyExists::class);

        $queryException = QueryExceptionStub::withCode('23000');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(true);
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->firstCommit($this->stream);
    }

    #[DataProvider('provideAnyOtherCodeThanStreamExists')]
    public function testRaiseQueryFailureOnCreation(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(true);
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->firstCommit($this->stream);
    }

    public function testRaiseStreamNotFoundOnAmend(): void
    {
        $this->expectException(StreamNotFound::class);

        $queryException = QueryExceptionStub::withCode('42S02');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    public function testConcurrencyExceptionRaisedOnAmend(): void
    {
        $this->expectException(ConnectionConcurrencyException::class);

        $queryException = QueryExceptionStub::withCode('23000');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new MysqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    #[DataProvider('provideAnyOtherCodeThanStreamExists')]
    public function testQueryFailureRaisedOnAmend(string $errorCode): void
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
