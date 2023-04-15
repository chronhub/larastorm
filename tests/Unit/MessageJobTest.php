<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Chronhub\Larastorm\Support\Producer\MessageJob;
use Chronhub\Larastorm\Tests\Stubs\Double\SomeCommand;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(MessageJob::class)]
final class MessageJobTest extends UnitTestCase
{
    public function testDefaultInstance(): void
    {
        $job = new MessageJob([]);

        $this->assertNull($job->connection);
        $this->assertNull($job->queue);
        $this->assertEquals(1, $job->tries);
        $this->assertNull($job->delay);
        $this->assertEquals(3, $job->maxExceptions);
        $this->assertEquals(30, $job->timeout);
        $this->assertNull($job->backoff);
    }

    public function testProperties(): void
    {
        $job = new MessageJob(
            [
                'headers' => [
                    'queue' => [
                        'connection' => 'redis',
                        'name' => 'transaction',
                        'tries' => 3,
                        'delay' => 10,
                        'max_exceptions' => 1,
                        'timeout' => 10,
                        'backoff' => 10,
                    ],
                ],
            ]);

        $this->assertEquals('redis', $job->connection);
        $this->assertEquals('transaction', $job->queue);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(10, $job->delay);
        $this->assertEquals(1, $job->maxExceptions);
        $this->assertEquals(10, $job->timeout);
        $this->assertEquals(10, $job->backoff);
    }

    public function testDisplayName(): void
    {
        $job = new MessageJob(
            [
                'headers' => [
                    Header::EVENT_TYPE => SomeCommand::class,
                ],
            ]);

        $this->assertEquals(SomeCommand::class, $job->displayName());
    }

    public function testQueueJob(): void
    {
        $payload = [
            'headers' => [
                'queue' => [
                    'name' => 'account',
                ],
            ],
        ];

        $job = new MessageJob($payload);

        $laravelQueue = $this->createMock(Queue::class);

        $laravelQueue->expects($this->once())
            ->method('pushOn')
            ->with('account', $job);

        $job->queue($laravelQueue, $job);
    }

    public function testHandleJob(): void
    {
        $payload = [
            'headers' => [
                Header::REPORTER_ID => 'reporter.command',
                'queue' => [
                    'name' => 'default',
                ],
            ],
        ];

        $container = Container::getInstance();
        $container->bind('reporter.command', function () use ($payload): Reporter {
            $reporter = $this->createMock(Reporter::class);

            $reporter->expects($this->once())
                ->method('relay')
                ->with($payload);

            return $reporter;
        });

        $job = new MessageJob($payload);

        $job->handle($container);
    }
}
