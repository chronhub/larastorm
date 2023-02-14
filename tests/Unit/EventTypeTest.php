<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use stdClass;
use Chronhub\Storm\Message\Message;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Larastorm\Support\MessageDecorator\EventType;

final class EventTypeTest extends UnitTestCase
{
    /**
     * @test
     */
    public function it_set_event_type_to_message_headers(): void
    {
        $messageDecorator = new EventType();

        $message = new Message(new stdClass());

        $this->assertEmpty($message->headers());

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertArrayHasKey(Header::EVENT_TYPE, $decoratedMessage->headers());

        $this->assertEquals(stdClass::class, $decoratedMessage->header(Header::EVENT_TYPE));
    }

    /**
     * @test
     */
    public function it_does_not_set_event_id_to_message_headers_if_already_exists(): void
    {
        $messageDecorator = new EventType();

        $message = new Message(new stdClass(), [
            Header::EVENT_TYPE => 'some_event_type',
        ]);

        $decoratedMessage = $messageDecorator->decorate($message);

        $this->assertEquals('some_event_type', $decoratedMessage->header(Header::EVENT_TYPE));
    }
}
