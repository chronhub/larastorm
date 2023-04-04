<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\PgsqlEventStore;
use Chronhub\Larastorm\Tests\Stubs\QueryExceptionStub;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Chronicler\Exceptions\StreamAlreadyExists;
use Chronhub\Larastorm\Support\Contracts\ChroniclerConnection;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;

#[CoversClass(PgsqlEventStore::class)]
final class PgsqlEventStoreTest extends UnitTestCase
{
    private MockObject|ChroniclerConnection $chronicler;

    private Stream $stream;

    protected function setUp(): void
    {
        $this->chronicler = $this->createMock(ChroniclerConnection::class);
        $this->stream = new Stream(new StreamName('customer'));
    }

    #[DataProvider('provideStreamExistsCode')]
    public function testRaiseStreamAlreadyExistsOnCreation(string $errorCode): void
    {
        $this->expectException(StreamAlreadyExists::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(true);
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($queryException);

        $chronicler = new PgsqlEventStore($this->chronicler);

        $chronicler->firstCommit($this->stream);
    }

    #[DataProvider('provideAnyOtherCodeThanStreamExists')]
    public function testRaiseQueryFailureOnCreation(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(true);
        $this->chronicler->expects($this->once())->method('firstCommit')->with($this->stream)->willThrowException($queryException);

        $chronicler = new PgsqlEventStore($this->chronicler);

        $chronicler->firstCommit($this->stream);
    }

    public function testRaiseStreamNotFoundOnAmend(): void
    {
        $this->expectException(StreamNotFound::class);

        $queryException = QueryExceptionStub::withCode('42P01');

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new PgsqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    #[DataProvider('provideStreamExistsCode')]
   public function testConcurrencyExceptionRaisedOnAmend(string $errorCode): void
   {
       $this->expectException(ConnectionConcurrencyException::class);

       $queryException = QueryExceptionStub::withCode($errorCode);

       $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
       $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

       $chronicler = new PgsqlEventStore($this->chronicler);

       $chronicler->amend($this->stream);
   }

    #[DataProvider('provideAnyOtherCodeThanStreamExists')]
    public function testQueryFailureRaisedOnAmend(string $errorCode): void
    {
        $this->expectException(ConnectionQueryFailure::class);

        $queryException = QueryExceptionStub::withCode($errorCode);

        $this->chronicler->expects($this->once())->method('isDuringCreation')->willReturn(false);
        $this->chronicler->expects($this->once())->method('amend')->with($this->stream)->willThrowException($queryException);

        $chronicler = new PgsqlEventStore($this->chronicler);

        $chronicler->amend($this->stream);
    }

    public static function provideAnyOtherCodeThanStreamExists(): Generator
    {
        yield ['40000'];
        yield ['00000'];
    }

    public static function provideStreamExistsCode(): Generator
    {
        yield ['23000'];
        yield ['23505'];
    }
}
