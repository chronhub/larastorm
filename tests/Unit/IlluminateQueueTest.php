<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Chronhub\Storm\Message\Message;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\Double\SomeCommand;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Larastorm\Support\Producer\MessageJob;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;

#[CoversClass(IlluminateQueue::class)]
final class IlluminateQueueTest extends UnitTestCase
{
    private MockObject|QueueingDispatcher $queue;

    private MockObject|MessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = $this->createMock(QueueingDispatcher::class);
        $this->serializer = $this->createMock(MessageSerializer::class);
    }

    #[Test]
    public function it_dispatch_serialize_message_to_queue(): void
    {
        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']));

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
        ];

        $this->serializer->expects($this->once())
            ->method('serializeMessage')
            ->with($message)
            ->willReturn($payload);

        $this->queue->expects($this->once())
            ->method('dispatchToQueue')
            ->with($this->callback(function (MessageJob $job) use ($payload): bool {
                $this->assertEquals($job->payload, $payload);

                return true;
            }));

        $illuminateQueue = new IlluminateQueue($this->queue, $this->serializer);

        $illuminateQueue->toQueue($message);
    }

    #[Test]
    public function it_add_queue_options_to_header(): void
    {
        $queueOptions = ['connection' => 'rabbitmq', 'name' => 'customer'];

        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']));

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
            'queue' => $queueOptions,
        ];

        $this->serializer->expects($this->once())
            ->method('serializeMessage')
            ->with($this->callback(function (Message $message) use ($queueOptions): bool {
                $this->assertArrayHasKey('queue', $message->headers());
                $this->assertEquals($queueOptions, $message->header('queue'));

                return true;
            }))
            ->willReturn($payload);

        $this->queue->expects($this->once())
            ->method('dispatchToQueue')
            ->with($this->callback(function (MessageJob $job) use ($payload): bool {
                $this->assertEquals($job->payload, $payload);

                return true;
            }));

        $illuminateQueue = new IlluminateQueue($this->queue, $this->serializer, $queueOptions);

        $illuminateQueue->toQueue($message);
    }

    #[Test]
    public function it_does_not_override_queue_header_if_already_exists_in_message(): void
    {
        $queueOptions = ['connection' => 'rabbitmq', 'name' => 'customer'];

        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']), ['queue' => ['name' => 'redis']]);

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
            'queue' => ['name' => 'redis'],
        ];

        $this->serializer->expects($this->once())
            ->method('serializeMessage')
            ->with($this->callback(function (Message $message): bool {
                $this->assertArrayHasKey('queue', $message->headers());
                $this->assertEquals('redis', $message->header('queue')['name']);

                return true;
            }))
            ->willReturn($payload);

        $this->queue->expects($this->once())
            ->method('dispatchToQueue')
            ->with($this->callback(function (MessageJob $job) use ($payload): bool {
                $this->assertEquals($job->payload, $payload);

                return true;
            }));

        $illuminateQueue = new IlluminateQueue($this->queue, $this->serializer, $queueOptions);

        $illuminateQueue->toQueue($message);
    }
}
