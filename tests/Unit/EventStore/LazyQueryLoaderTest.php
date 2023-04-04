<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Chronicler\Exceptions\InvalidArgumentException;

#[CoversClass(LazyQueryLoader::class)]
final class LazyQueryLoaderTest extends UnitTestCase
{
    #[DataProvider('provideChunkSize')]
    public function testInstance(int $chunkSize): void
    {
        $someEvent = SomeEvent::fromContent(['foo' => 'bar']);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('lazy')
            ->with($chunkSize)
            ->willReturn(new LazyCollection([['some' => 'payload']]));

        $serializer = $this->createMock(StreamEventSerializer::class);
        $serializer->expects($this->once())
            ->method('deserializePayload')
            ->with(['some' => 'payload'])
            ->willReturn($someEvent);

        $eventLoader = new EventLoader($serializer);

        $lazyQueryLoader = new LazyQueryLoader($eventLoader, $chunkSize);

        $generator = $lazyQueryLoader->query($builder, new StreamName('foo'));

        $this->assertEquals($someEvent, $generator->current());

        $generator->next();

        $this->assertEquals(1, $generator->getReturn());
    }

    public function testDefaultChunkSize(): void
    {
        $serializer = $this->createMock(StreamEventSerializer::class);

        $eventLoader = new EventLoader($serializer);

        $lazyQueryLoader = new LazyQueryLoader($eventLoader);

        $this->assertSame(1000, $lazyQueryLoader->chunkSize);
    }

    #[DataProvider('provideChunkSize')]
    public function testChunkSize(int $chunkSize): void
    {
        $serializer = $this->createMock(StreamEventSerializer::class);

        $eventLoader = new EventLoader($serializer);

        $lazyQueryLoader = new LazyQueryLoader($eventLoader, $chunkSize);

        $this->assertSame($chunkSize, $lazyQueryLoader->chunkSize);
    }

    #[DataProvider('provideInvalidChunkSize')]
    public function testExceptionRaisedWhenChunkSizeIsLessThanOne(int $invalidChunkSize): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk size must be greater than 0');

        $serializer = $this->createMock(StreamEventSerializer::class);

        $eventLoader = new EventLoader($serializer);

        $lazyQueryLoader = new LazyQueryLoader($eventLoader, $invalidChunkSize);

        $this->assertSame($invalidChunkSize, $lazyQueryLoader->chunkSize);
    }

    public static function provideChunkSize(): Generator
    {
        yield [1];
        yield [10];
        yield [100];
    }

    public static function provideInvalidChunkSize(): Generator
    {
        yield [0];
        yield [-1];
    }
}
