<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\MessageDecorator;

use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Reporter\OnDispatchPriority;
use Chronhub\Storm\Contracts\Tracker\MessageStory;
use Chronhub\Storm\Reporter\DetachMessageListener;
use Chronhub\Storm\Contracts\Tracker\MessageTracker;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;

final class DecorateMessage implements MessageSubscriber
{
    use DetachMessageListener;

    public function __construct(private readonly MessageDecorator $messageDecorator)
    {
    }

    public function attachToReporter(MessageTracker $tracker): void
    {
        $this->messageListeners[] = $tracker->watch(Reporter::DISPATCH_EVENT, function (MessageStory $story): void {
            $message = $this->messageDecorator->decorate($story->message());

            $story->withMessage($message);
        }, OnDispatchPriority::MESSAGE_DECORATOR->value);
    }
}
