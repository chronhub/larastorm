<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\MessageDecorator;

use Chronhub\Storm\Message\Message;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Contracts\Message\MessageDecorator;

final readonly class EventTime implements MessageDecorator
{
    public function __construct(private SystemClock $clock)
    {
    }

    public function decorate(Message $message): Message
    {
        if ($message->hasNot(Header::EVENT_TIME)) {
            $message = $message->withHeader(Header::EVENT_TIME, $this->clock->now());
        }

        return $message;
    }
}
