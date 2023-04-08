<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Producer;

use Chronhub\Storm\Contracts\Producer\MessageQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Message\Message;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use function array_merge;
use function is_array;

final readonly class IlluminateQueue implements MessageQueue
{
    public function __construct(
        public QueueingDispatcher $queueingDispatcher,
        public MessageSerializer $messageSerializer,
        public ?array $groupQueueOptions = null
    ) {
    }

    public function toQueue(Message $message): void
    {
        if (is_array($this->groupQueueOptions)) {
            $options = $message->header('queue') ?? [];

            $message = $message->withHeader('queue', array_merge($this->groupQueueOptions, $options));
        }

        $payload = $this->messageSerializer->serializeMessage($message);

        $this->queueingDispatcher->dispatchToQueue(new MessageJob($payload));
    }
}
