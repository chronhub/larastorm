<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Projection\Events\ProjectionDeleted;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectionDeleted::class)]
class ProjectionDeletedTest extends UnitTestCase
{
    public function testEvent(): void
    {
        $event = new ProjectionDeleted('stream_name');

        $this->assertEquals('stream_name', $event->streamName);
    }
}
