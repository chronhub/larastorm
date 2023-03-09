<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Projection\Events\ProjectionStarted;

#[CoversClass(ProjectionStarted::class)]
class ProjectionStartedTest extends UnitTestCase
{
    #[Test]
    public function it_test_event(): void
    {
        $event = new ProjectionStarted('stream_name');

        $this->assertEquals('stream_name', $event->streamName);
    }
}
