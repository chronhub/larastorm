<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Prophecy\Argument;
use Chronhub\Storm\Message\Message;
use Prophecy\Prophecy\ObjectProphecy;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Larastorm\Tests\Double\SomeCommand;
use Illuminate\Contracts\Bus\QueueingDispatcher;
use Chronhub\Larastorm\Support\Producer\MessageJob;
use Chronhub\Larastorm\Support\Producer\IlluminateQueue;
use Chronhub\Storm\Contracts\Serializer\MessageSerializer;

final class IlluminateQueueTest extends ProphecyTestCase
{
    private ObjectProphecy|QueueingDispatcher $queue;

    private ObjectProphecy|MessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = $this->prophesize(QueueingDispatcher::class);
        $this->serializer = $this->prophesize(MessageSerializer::class);
    }

    /**
     * @test
     */
    public function it_dispatch_serialize_message_to_queue(): void
    {
        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']));

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
        ];

        $this->serializer->serializeMessage($message)->willReturn($payload)->shouldBeCalledOnce();
        $this->queue->dispatchToQueue(Argument::that(function (MessageJob $job) use ($payload): array {
            $this->assertEquals($job->payload, $payload);

            return $payload;
        }))->shouldBeCalledOnce();

        $illuminateQueue = new IlluminateQueue($this->queue->reveal(), $this->serializer->reveal());

        $illuminateQueue->toQueue($message);
    }

    /**
     * @test
     */
    public function it_add_queue_options_to_header(): void
    {
        $queueOptions = ['connection' => 'rabbitmq', 'name' => 'customer'];

        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']));

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
            'queue' => $queueOptions,
        ];

        $this->serializer->serializeMessage(Argument::that(function (Message $message) use ($queueOptions): Message {
            $this->assertArrayHasKey('queue', $message->headers());
            $this->assertEquals($queueOptions, $message->header('queue'));

            return $message;
        }))->willReturn($payload)->shouldBeCalledOnce();

        $this->queue->dispatchToQueue(Argument::that(function (MessageJob $job) use ($payload): array {
            $this->assertEquals($job->payload, $payload);

            return $payload;
        }))->shouldBeCalledOnce();

        $illuminateQueue = new IlluminateQueue($this->queue->reveal(), $this->serializer->reveal(), $queueOptions);

        $illuminateQueue->toQueue($message);
    }

    /**
     * @test
     */
    public function it_does_not_override_queue_header_if_already_exists_in_message(): void
    {
        $queueOptions = ['connection' => 'rabbitmq', 'name' => 'customer'];

        $message = new Message(SomeCommand::fromContent(['name' => 'steph bug']), ['queue' => ['name' => 'redis']]);

        $payload = [
            'headers' => [],
            'content' => 'steph bug',
            'queue' => ['name' => 'redis'],
        ];

        $this->serializer->serializeMessage(Argument::that(function (Message $message): Message {
            $this->assertArrayHasKey('queue', $message->headers());
            $this->assertEquals('redis', $message->header('queue')['name']);

            return $message;
        }))->willReturn($payload)->shouldBeCalledOnce();

        $this->queue->dispatchToQueue(Argument::that(function (MessageJob $job) use ($payload): array {
            $this->assertEquals($job->payload, $payload);

            return $payload;
        }))->shouldBeCalledOnce();

        $illuminateQueue = new IlluminateQueue($this->queue->reveal(), $this->serializer->reveal(), $queueOptions);

        $illuminateQueue->toQueue($message);
    }
}
