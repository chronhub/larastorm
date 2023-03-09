<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Projection\Events\ProjectionDeleted;

#[CoversClass(ProjectionDeleted::class)]
class ProjectionDeletedTest extends UnitTestCase
{
    #[Test]
    public function it_test_event(): void
    {
        $event = new ProjectionDeleted('stream_name', false);

        $this->assertEquals('stream_name', $event->streamName);
        $this->assertFalse($event->withEmittedEvents);
    }

    #[Test]
    public function it_test_event_with_emitted_events(): void
    {
        $event = new ProjectionDeleted('stream_name', true);

        $this->assertEquals('stream_name', $event->streamName);
        $this->assertTrue($event->withEmittedEvents);
    }
}
