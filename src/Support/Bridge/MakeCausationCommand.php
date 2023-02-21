<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Bridge;

use Chronhub\Storm\Message\Message;
use Chronhub\Storm\Reporter\DomainCommand;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Tracker\Listener;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Storm\Contracts\Tracker\StreamStory;
use Chronhub\Storm\Contracts\Tracker\MessageStory;
use Chronhub\Storm\Reporter\DetachMessageListener;
use Chronhub\Storm\Contracts\Tracker\MessageTracker;
use Chronhub\Storm\Contracts\Message\MessageDecorator;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Contracts\Chronicler\StreamSubscriber;
use Chronhub\Storm\Contracts\Chronicler\EventableChronicler;

final class MakeCausationCommand implements MessageSubscriber, StreamSubscriber
{
    use DetachMessageListener;

    private ?DomainCommand  $currentCommand = null;

    /**
     * @var array<Listener>
     */
    private array $streamListeners = [];

    public function attachToReporter(MessageTracker $tracker): void
    {
        $this->messageListeners[] = $tracker->watch(Reporter::DISPATCH_EVENT, function (MessageStory $story): void {
            $command = $story->message()->event();

            if ($command instanceof DomainCommand) {
                $this->currentCommand = $command;
            }
        }, 1000);

        $this->messageListeners[] = $tracker->watch(Reporter::FINALIZE_EVENT, function (): void {
            $this->currentCommand = null;
        }, 1000);
    }

    public function attachToChronicler(EventableChronicler $chronicler): void
    {
        $eventNames = [EventableChronicler::PERSIST_STREAM_EVENT, EventableChronicler::FIRST_COMMIT_EVENT];

        foreach ($eventNames as $eventName) {
            $this->streamListeners[] = $chronicler->subscribe(
                $eventName,
                function (StreamStory $story): void {
                    if ($this->currentCommand instanceof DomainCommand) {
                        $messageDecorator = $this->correlationMessageDecorator();

                        $story->decorate($messageDecorator);
                    }
                },
                100
            );
        }
    }

    public function detachFromChronicler(EventableChronicler $chronicler): void
    {
        $chronicler->unsubscribe(...$this->streamListeners);
    }

    private function correlationMessageDecorator(): MessageDecorator
    {
        $eventId = (string) $this->currentCommand->header(Header::EVENT_ID);

        $eventType = $this->currentCommand->header(Header::EVENT_TYPE);

        return new class($eventId, $eventType) implements MessageDecorator
        {
            public function __construct(private readonly string $eventId,
                                        private readonly string $eventType)
            {
            }

            public function decorate(Message $message): Message
            {
                if ($message->has(EventHeader::EVENT_CAUSATION_ID)
                    && $message->has(EventHeader::EVENT_CAUSATION_TYPE)) {
                    return $message;
                }

                $causationHeaders = [
                    EventHeader::EVENT_CAUSATION_ID => $this->eventId,
                    EventHeader::EVENT_CAUSATION_TYPE => $this->eventType,
                ];

                return $message->withHeaders($message->headers() + $causationHeaders);
            }
        };
    }
}
