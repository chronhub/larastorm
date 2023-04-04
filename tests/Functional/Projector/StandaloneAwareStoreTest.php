<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use Generator;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Projector\ProjectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Projection\Events\ProjectionReset;
use Chronhub\Larastorm\Projection\Events\ProjectionDeleted;
use Chronhub\Larastorm\Projection\Events\ProjectionOnError;
use Chronhub\Larastorm\Projection\Events\ProjectionStarted;
use Chronhub\Larastorm\Projection\Events\ProjectionStopped;
use Chronhub\Larastorm\Projection\DispatcherAwareRepository;
use Chronhub\Larastorm\Projection\Events\ProjectionRestarted;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;

#[CoversClass(DispatcherAwareRepository::class)]
class StandaloneAwareStoreTest extends UnitTestCase
{
    private ProjectionRepositoryInterface|MockObject $store;

    private Dispatcher|MockObject $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = $this->createMock(ProjectionRepositoryInterface::class);
        $this->store->method('projectionName')->willReturn('stream_name');
        $this->eventDispatcher = $this->createMock(Dispatcher::class);
    }

    #[Test]
    public function it_dispatch_event_when_projection_started(): void
    {
        $this->store->expects($this->once())->method('create')->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionStarted::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->create();
    }

    #[Test]
    public function it_dispatch_event_exception_when_projection_started(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('create')->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->create();
    }

    #[Test]
    public function it_dispatch_event_when_projection_restarted(): void
    {
        $this->store->expects($this->once())->method('startAgain')->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionRestarted::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->startAgain();
    }

    #[Test]
    public function it_dispatch_event_exception_when_projection_restarted(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('startAgain')->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->startAgain();
    }

    #[Test]
    public function it_dispatch_event_when_projection_stopped(): void
    {
        $this->store->expects($this->once())->method('stop')->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionStopped::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->stop();
    }

    #[Test]
    public function it_dispatch_event_exception_when_projection_stopped(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('stop')->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->stop();
    }

    #[Test]
    public function it_dispatch_event_when_projection_reset(): void
    {
        $this->store->expects($this->once())->method('reset')->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionReset::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->reset();
    }

    #[Test]
    public function it_dispatch_event_exception_when_projection_reset(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('reset')->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->reset();
    }

    #[Test]
    public function it_dispatch_event_when_projection_deleted(): void
    {
        $this->store->expects($this->once())->method('delete')->with(false)->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionDeleted::class, $event::class);
                $this->assertFalse($event->withEmittedEvents);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->delete(false);
    }

    #[Test]
    public function it_dispatch_event_exception_when_projection_deleted(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('delete')->with(false)->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->delete(false);
    }

    #[Test]
    public function it_dispatch_event_when_projection_deleted_with_emitted_events(): void
    {
        $this->store->expects($this->once())->method('delete')->with(true)->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionDeleted::class, $event::class);
                $this->assertTrue($event->withEmittedEvents);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->delete(true);
    }

    #[Test]
    public function it_dispatch_event_exception_when_projection_deleted_with_emitted_event(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('delete')->with(true)->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->delete(true);
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_acquire_lock($lockAcquired): void
    {
        $this->store->expects($this->once())->method('acquireLock')->willReturn($lockAcquired);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($lockAcquired, $store->acquireLock());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_release_lock($lockReleased): void
    {
        $this->store->expects($this->once())->method('releaseLock')->willReturn($lockReleased);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($lockReleased, $store->releaseLock());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_update_lock($lockUpdated): void
    {
        $this->store->expects($this->once())->method('updateLock')->willReturn($lockUpdated);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($lockUpdated, $store->updateLock());
    }

    #[Test]
    public function it_load_status(): void
    {
        $this->store->expects($this->once())->method('loadStatus')->willReturn(ProjectionStatus::IDLE);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals(ProjectionStatus::IDLE, $store->loadStatus());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_persist(bool $isPersisted): void
    {
        $this->store->expects($this->once())->method('persist')->willReturn($isPersisted);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($isPersisted, $store->persist());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_check_stream_exits(bool $streamExists): void
    {
        $this->store->expects($this->once())->method('exists')->willReturn($streamExists);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($streamExists, $store->exists());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_load_state(bool $stateLoaded): void
    {
        $this->store->expects($this->once())->method('loadState')->willReturn($stateLoaded);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($stateLoaded, $store->loadState());
    }

    #[Test]
    public function it_return_current_stream_name(): void
    {
        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals('stream_name', $store->projectionName());
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
