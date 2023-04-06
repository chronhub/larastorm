<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\EventStore;

use Chronhub\Larastorm\EventStore\Loader\CursorQueryLoader;
use Chronhub\Larastorm\EventStore\Loader\EventLoader;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeEvent;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Serializer\StreamEventSerializer;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\LazyCollection;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CursorQueryLoader::class)]
final class CursorQueryLoaderTest extends UnitTestCase
{
    public function testInstance(): void
    {
        $someEvent = SomeEvent::fromContent(['foo' => 'bar']);

        $builder = $this->createMock(Builder::class);
        $builder->expects($this->once())
            ->method('cursor')
            ->willReturn(new LazyCollection([['some' => 'payload']]));

        $serializer = $this->createMock(StreamEventSerializer::class);

        $serializer->expects($this->once())
            ->method('deserializePayload')
            ->with(['some' => 'payload'])
            ->willReturn($someEvent);

        $eventLoader = new EventLoader($serializer);

        $cursorEventLoader = new CursorQueryLoader($eventLoader);

        $generator = $cursorEventLoader->query($builder, new StreamName('foo'));

        $this->assertEquals($someEvent, $generator->current());

        $generator->next();

        $this->assertEquals(1, $generator->getReturn());
    }
}
