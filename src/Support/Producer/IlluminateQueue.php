<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Producer;

use Chronhub\Storm\Message\Message;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Storm\Contracts\Producer\MessageQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use function count;
use function is_array;

final readonly class IlluminateQueue implements MessageQueue
{
    public function __construct(public QueueingDispatcher $queueingDispatcher,
                                public MessageSerializer $messageSerializer,
                                public ?array $groupQueueOptions = null)
    {
    }

    public function toQueue(Message $message): void
    {
        if (is_array($this->groupQueueOptions) && count($this->groupQueueOptions) > 0 && $message->hasNot('queue')) {
            $message = $message->withHeader('queue', $this->groupQueueOptions);
        }

        $payload = $this->messageSerializer->serializeMessage($message);

        $this->queueingDispatcher->dispatchToQueue(new MessageJob($payload));
    }
}
