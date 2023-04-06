<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Projection\Events\ProjectionOnError;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Error;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;

#[CoversClass(ProjectionOnError::class)]
class ProjectionOnErrorTest extends UnitTestCase
{
    #[DataProvider('provideException')]
    #[Test]
    public function it_test_event(Throwable $exception): void
    {
        $event = new ProjectionOnError('stream_name', $exception);

        $this->assertEquals('stream_name', $event->streamName);
        $this->assertEquals($exception, $event->exception);
    }

    public static function provideException(): Generator
    {
        yield [new RuntimeException('error_message')];
        yield [new Error('error_message')];
    }
}
