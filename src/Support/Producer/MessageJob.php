<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Support\Producer;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\InteractsWithQueue;
use Chronhub\Storm\Contracts\Message\Header;
use Illuminate\Contracts\Container\Container;

final class MessageJob
{
    use InteractsWithQueue;
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The maximum number of unhandled exceptions to allow before failing
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds the job can run before timing out
     */
    public int $timeout = 30;

    public null|int $backoff = null;

    public function __construct(public readonly array $payload)
    {
        $this->setQueueOptions($this->payload['headers']['queue'] ?? []);
    }

    public function handle(Container $container): void
    {
        $container[$this->payload['headers'][Header::REPORTER_ID]]->relay($this->payload);
    }

    /**
     * Internally used by laravel
     */
    public function queue(Queue $queue, self $messageJob): void
    {
        $queue->pushOn($this->queue, $messageJob);
    }

    /**
     * Display message name
     */
    public function displayName(): string
    {
        return $this->payload['headers'][Header::EVENT_TYPE];
    }

    private function setQueueOptions(array $queue): void
    {
        $this->connection = $queue['connection'] ?? $this->connection;
        $this->queue = $queue['name'] ?? $this->queue;
        $this->tries = $queue['tries'] ?? $this->tries;
        $this->delay = $queue['delay'] ?? $this->delay;
        $this->maxExceptions = $queue['max_exceptions'] ?? $this->maxExceptions;
        $this->timeout = $queue['timeout'] ?? $this->timeout;
        $this->backoff = $queue['backoff'] ?? $this->backoff;
    }
}
