<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Validation;

use Chronhub\Storm\Contracts\Producer\ProducerUnity;
use Chronhub\Storm\Contracts\Tracker\MessageStory;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Contracts\Tracker\MessageTracker;
use Chronhub\Storm\Message\Message;
use Chronhub\Storm\Reporter\DetachMessageListener;
use Chronhub\Storm\Reporter\OnDispatchPriority;
use Illuminate\Contracts\Validation\Factory;

final class ValidateOnlyOnceMessage implements MessageSubscriber
{
    use DetachMessageListener;

    public function __construct(
        private readonly Factory $validation,
        private readonly ProducerUnity $producerUnity
    ) {
    }

    public function attachToReporter(MessageTracker $tracker): void
    {
        $this->messageListeners[] = $tracker->onDispatch(
            function (MessageStory $story): void {
                $message = $story->message();

                if (! $message->isMessaging() || ! $message->event() instanceof MessageValidation) {
                    return;
                }

                $this->conditionallyValidateEvent($message);
        }, OnDispatchPriority::MESSAGE_VALIDATION->value);
    }

    private function conditionallyValidateEvent(Message $message): void
    {
        $event = $message->event();
        $sync = $this->producerUnity->isSync($message);

        if ($event instanceof MessagePreValidation && ! $sync) {
            $this->validateMessage($event, $message);
        } elseif (! $event instanceof MessagePreValidation && $sync) {
            $this->validateMessage($event, $message);
        }
    }

    private function validateMessage(MessageValidation $event, Message $message): void
    {
        $validator = $this->validation->make($event->toContent(), $event->validationRules());

        if ($validator->fails()) {
            throw ValidationMessageFailed::withValidator($validator, $message);
        }
    }
}
