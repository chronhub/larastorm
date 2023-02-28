<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Generator;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\EventStore\Loader\LazyQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

final class LazyQueryLoaderTest extends ProphecyTestCase
{
    /**
     * @test
     *
     * @dataProvider provideChunkSize
     */
    public function it_can_be_instantiated(int $chunkSize): void
    {
        $someEvent = SomeEvent::fromContent(['foo' => 'bar']);

        $builder = $this->prophesize(Builder::class);
        $builder->lazy($chunkSize)->willReturn(new LazyCollection([['some' => 'payload']]))->shouldBeCalledOnce();

        $serializer = $this->prophesize(StreamEventSerializer::class);
        $serializer->unserializeContent(['some' => 'payload'])->willYield([$someEvent])->shouldBeCalledOnce();

        $eventLoader = new EventLoader($serializer->reveal());

        $lazyQueryLoader = new LazyQueryLoader($eventLoader, $chunkSize);

        $generator = $lazyQueryLoader->query($builder->reveal(), new StreamName('foo'));

        $this->assertEquals($someEvent, $generator->current());

        $generator->next();

        $this->assertEquals(1, $generator->getReturn());
    }

    public function provideChunkSize(): Generator
    {
        yield [1];
        yield [10];
        yield [100];
    }
}
