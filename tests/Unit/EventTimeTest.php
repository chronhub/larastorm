<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use stdClass;
use DateInterval;
use DateTimeZone;
use DateTimeImmutable;
use Chronhub\Storm\Message\Message;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Message\Header;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Larastorm\Support\MessageDecorator\EventTime;

#[CoversClass(EventTime::class)]
final class EventTimeTest extends UnitTestCase
{
    private MockObject|SystemClock $clock;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = $this->createMock(SystemClock::class);
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    #[Test]
    public function it_set_event_id_to_message_headers(): void
    {
        $this->clock->expects($this->once())
            ->method('now')
            ->willReturn($this->now);

        $messageDecorator = new EventTime($this->clock);

        $message = new Message(new stdClass());

        $this->assertEmpty($message->headers());

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertArrayHasKey(Header::EVENT_TIME, $decoratedMessage->headers());

        $this->assertEquals($this->now, $decoratedMessage->header(Header::EVENT_TIME));
    }

    #[Test]
    public function it_does_not_set_event_time_to_message_headers_if_already_exists(): void
    {
        $pastEventTime = $this->now->sub(new DateInterval('PT1H'));

        $messageDecorator = new EventTime($this->clock);

        $message = new Message(new stdClass(), [Header::EVENT_TIME => $pastEventTime]);

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertSame($pastEventTime, $decoratedMessage->header(Header::EVENT_TIME));
    }
}
