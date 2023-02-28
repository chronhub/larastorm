<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

final class CursorQueryLoaderTest extends ProphecyTestCase
{
    /**
     * @test
     */
    public function it_can_be_instantiated(): void
    {
        $someEvent = SomeEvent::fromContent(['foo' => 'bar']);

        $builder = $this->prophesize(Builder::class);
        $builder->cursor()->willReturn(new LazyCollection([['some' => 'payload']]))->shouldBeCalledOnce();

        $serializer = $this->prophesize(StreamEventSerializer::class);
        $serializer->unserializeContent(['some' => 'payload'])->willYield([$someEvent])->shouldBeCalledOnce();

        $eventLoader = new EventLoader($serializer->reveal());

        $cursorEventLoader = new CursorQueryLoader($eventLoader);

        $generator = $cursorEventLoader->query($builder->reveal(), new StreamName('foo'));

        $this->assertEquals($someEvent, $generator->current());

        $generator->next();

        $this->assertEquals(1, $generator->getReturn());
    }
}
