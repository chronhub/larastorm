<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Projection\Events\ProjectionStopped;

#[CoversClass(ProjectionStopped::class)]
class ProjectionStoppedTest extends UnitTestCase
{
    #[Test]
    public function it_test_event(): void
    {
        $event = new ProjectionStopped('stream_name');

        $this->assertEquals('stream_name', $event->streamName);
    }
}
