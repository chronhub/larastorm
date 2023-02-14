<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use stdClass;
use Symfony\Component\Uid\Uuid;
use Chronhub\Storm\Message\Message;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Larastorm\Support\MessageDecorator\EventId;

final class EventIdTest extends UnitTestCase
{
    /**
     * @test
     */
    public function it_set_event_id_to_message_headers(): void
    {
        $messageDecorator = new EventId();

        $message = new Message(new stdClass());

        $this->assertEmpty($message->headers());

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertArrayHasKey(Header::EVENT_ID, $decoratedMessage->headers());

        $this->assertInstanceOf(Uuid::class, $decoratedMessage->header(Header::EVENT_ID));
    }

    /**
     * @test
     */
    public function it_does_not_set_event_id_to_message_headers_if_already_exists(): void
    {
        $messageDecorator = new EventId();

        $message = new Message(new stdClass(), [
            Header::EVENT_ID => 'some_event_id',
        ]);

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertEquals('some_event_id', $decoratedMessage->header(Header::EVENT_ID));
    }
}
