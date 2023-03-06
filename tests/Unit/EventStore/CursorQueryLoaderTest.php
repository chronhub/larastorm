<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Double\SomeEvent;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;

#[CoversClass(CursorQueryLoader::class)]
final class CursorQueryLoaderTest extends UnitTestCase
{
    #[Test]
    public function it_can_be_instantiated(): void
    {
        $someEvent = SomeEvent::fromContent(['foo' => 'bar']);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('cursor')
            ->willReturn(new LazyCollection([['some' => 'payload']]));

        $serializer = $this->createMock(StreamEventSerializer::class);

        $serializer->expects($this->once())
            ->method('unserializeContent')
            ->with(['some' => 'payload'])
            ->will($this->returnCallback(function () use ($someEvent) {
                yield $someEvent;

                return 1;
            }));

        $eventLoader = new EventLoader($serializer);

        $cursorEventLoader = new CursorQueryLoader($eventLoader);

        $generator = $cursorEventLoader->query($builder, new StreamName('foo'));

        $this->assertEquals($someEvent, $generator->current());

        $generator->next();

        $this->assertEquals(1, $generator->getReturn());
    }
}
