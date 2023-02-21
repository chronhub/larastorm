<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Bridge;

use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Storm\Reporter\OnDispatchPriority;
use Chronhub\Storm\Reporter\OnFinalizePriority;
use Chronhub\Storm\Contracts\Tracker\MessageStory;
use Chronhub\Storm\Reporter\DetachMessageListener;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Tracker\MessageTracker;
use Chronhub\Storm\Contracts\Tracker\MessageSubscriber;
use Chronhub\Storm\Contracts\Chronicler\TransactionalEventableChronicler;

final class HandleTransactionalCommand implements MessageSubscriber
{
    use DetachMessageListener;

    public function __construct(private readonly Chronicler $chronicler)
    {
    }

    public function attachToReporter(MessageTracker $tracker): void
    {
        $this->messageListeners[] = $tracker->watch(Reporter::DISPATCH_EVENT, function (): void {
            if ($this->chronicler instanceof TransactionalEventableChronicler) {
                $this->chronicler->beginTransaction();
            }
        }, OnDispatchPriority::START_TRANSACTION->value);

        $this->messageListeners[] = $tracker->watch(Reporter::FINALIZE_EVENT, function (MessageStory $story): void {
            if ($this->chronicler instanceof TransactionalEventableChronicler && $this->chronicler->inTransaction()) {
                $story->hasException()
                    ? $this->chronicler->rollbackTransaction()
                    : $this->chronicler->commitTransaction();
            }
        }, OnFinalizePriority::FINALIZE_TRANSACTION->value);
    }
}
