<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Larastorm\Support\Producer\MessageJob;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeCommand;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;
use Chronhub\Storm\Message\Message;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;

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

    public function testQueueSerializedMessage(): void
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

    public function testAddQueueOptionsToHeaders(): void
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

    public function testMergeQueueOptionsToGroupQueueOptions(): void
    {
        $queueOptions = ['connection' => 'rabbitmq', 'name' => 'default'];

        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']), ['queue' => ['name' => 'redis']]);

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
            'queue' => ['connection' => 'rabbitmq', 'name' => 'redis'],
        ];

        $this->serializer->expects($this->once())
            ->method('serializeMessage')
            ->with($this->callback(function (Message $message): bool {
                $this->assertArrayHasKey('queue', $message->headers());
                $this->assertEquals('redis', $message->header('queue')['name']);
                $this->assertEquals('rabbitmq', $message->header('queue')['connection']);

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
