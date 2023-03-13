<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

#[CoversClass(LazyQueryLoader::class)]
final class LazyQueryLoaderTest extends UnitTestCase
{
    #[DataProvider('provideChunkSize')]
    #[Test]
    public function it_can_be_instantiated(int $chunkSize): void
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

    public static function provideChunkSize(): Generator
    {
        yield [1];
        yield [10];
        yield [100];
    }
}
