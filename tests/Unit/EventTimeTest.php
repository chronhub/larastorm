<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use stdClass;
use DateInterval;
use DateTimeZone;
use DateTimeImmutable;
use Chronhub\Storm\Message\Message;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Larastorm\Support\MessageDecorator\EventId;
use Chronhub\Larastorm\Support\MessageDecorator\EventTime;

final class EventTimeTest extends ProphecyTestCase
{
    private ObjectProphecy|SystemClock $clock;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clock = $this->prophesize(SystemClock::class);
        $this->now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /**
     * @test
     */
    public function it_set_event_id_to_message_headers(): void
    {
        $this->clock->now()->willReturn($this->now)->shouldBeCalledOnce();

        $messageDecorator = new EventTime($this->clock->reveal());

        $message = new Message(new stdClass());

        $this->assertEmpty($message->headers());

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertArrayHasKey(Header::EVENT_TIME, $decoratedMessage->headers());

        $this->assertEquals($this->now, $decoratedMessage->header(Header::EVENT_TIME));
    }

    /**
     * @test
     */
    public function it_does_not_set_event_id_to_message_headers_if_already_exists(): void
    {
        $pastEventTime = $this->now->sub(new DateInterval('PT1H'));

        $messageDecorator = new EventId();

        $message = new Message(new stdClass(), [Header::EVENT_TIME => $pastEventTime]);

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertSame($pastEventTime, $decoratedMessage->header(Header::EVENT_TIME));
    }
}
