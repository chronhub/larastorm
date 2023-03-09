<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Projection\Events\ProjectionReset;

#[CoversClass(ProjectionReset::class)]
class ProjectionResetTest extends UnitTestCase
{
    #[Test]
    public function it_test_event(): void
    {
        $event = new ProjectionReset('stream_name');

        $this->assertEquals('stream_name', $event->streamName);
    }
}
