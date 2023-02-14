<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Contracts\Reporter\Reporter;
use Chronhub\Larastorm\Tests\Double\SomeCommand;
use Chronhub\Larastorm\Support\Producer\MessageJob;

final class MessageJobTest extends ProphecyTestCase
{
    /**
     * @test
     */
    public function it_assert_default_properties(): void
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

    /**
     * @test
     */
    public function it_set_properties(): void
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

    /**
     * @test
     */
    public function it_display_name_from_payload_headers_event_type(): void
    {
        $job = new MessageJob(
            [
                'headers' => [
                    Header::EVENT_TYPE => SomeCommand::class,
                ],
            ]);

        $this->assertEquals(SomeCommand::class, $job->displayName());
    }

    /**
     * @test
     */
    public function it_queue_job(): void
    {
        $payload = [
            'headers' => [
                'queue' => [
                    'name' => 'account',
                ],
            ],
        ];

        $job = new MessageJob($payload);

        $laravelQueue = $this->prophesize(Queue::class);
        $laravelQueue->pushOn('account', $job)->shouldBeCalledOnce();

        $job->queue($laravelQueue->reveal(), $job);
    }

    /**
     * @test
     */
    public function it_handle_job(): void
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
            $reporter = $this->prophesize(Reporter::class);
            $reporter->relay($payload)->shouldBeCalledOnce();

            return $reporter->reveal();
        });

        $job = new MessageJob($payload);

        $job->handle($container);
    }
}
