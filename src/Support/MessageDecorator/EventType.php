<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\MessageDecorator;

use Chronhub\Storm\Message\Message;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Message\MessageDecorator;

final class EventType implements MessageDecorator
{
    public function decorate(Message $message): Message
    {
        if ($message->hasNot(Header::EVENT_TYPE)) {
            $message = $message->withHeader(Header::EVENT_TYPE, $message->event()::class);
        }

        return $message;
    }
}
