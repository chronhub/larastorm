<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use Chronhub\Larastorm\Projection\DispatcherAwareRepository;
use Chronhub\Larastorm\Projection\Events\ProjectionDeleted;
use Chronhub\Larastorm\Projection\Events\ProjectionOnError;
use Chronhub\Larastorm\Projection\Events\ProjectionReset;
use Chronhub\Larastorm\Projection\Events\ProjectionRestarted;
use Chronhub\Larastorm\Projection\Events\ProjectionStarted;
use Chronhub\Larastorm\Projection\Events\ProjectionStopped;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;
use Chronhub\Storm\Projector\ProjectionStatus;
use Generator;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

#[CoversClass(DispatcherAwareRepository::class)]
class DispatcherAwareRepositoryTest extends UnitTestCase
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

    public function testDispatchEventOnProjectionStarted(): void
    {
        $this->store
            ->expects($this->once())
            ->method('create')
            ->with(ProjectionStatus::RUNNING)
            ->willReturn(true);

        $this->eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionStarted::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->create(ProjectionStatus::RUNNING);
    }

    public function testDispatchExceptionOnExceptionRaisedWhenStartingProjection(): void
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

        $store->create(ProjectionStatus::RUNNING);
    }

    public function testDispatchEventOnProjectionStartedAgain(): void
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

    public function testDispatchExceptionOnExceptionRaisedWhenRestartingProjection(): void
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

    public function testDispatchEventOnProjectionStopped(): void
    {
        $this->store
            ->expects($this->once())
            ->method('stop')
            ->with(['foo' => 10], ['bar'])
            ->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionStopped::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->stop(['foo' => 10], ['bar']);
    }

    public function testDispatchExceptionOnExceptionRaisedWhenStoppingProjection(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store
            ->expects($this->once())
            ->method('stop')
            ->with(['foo' => 1], ['bar'])
            ->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->stop(['foo' => 1], ['bar']);
    }

    public function testDispatchEventOnProjectionReset(): void
    {
        $this->store
            ->expects($this->once())
            ->method('reset')
            ->with(['foo' => 1], ['bar'], ProjectionStatus::RESETTING)
            ->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionReset::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->reset(['foo' => 1], ['bar'], ProjectionStatus::RESETTING);
    }

    public function testDispatchExceptionOnExceptionRaisedWhenResettingProjection(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store
            ->expects($this->once())
            ->method('reset')
            ->with(['foo' => 1], ['bar'], ProjectionStatus::RESETTING)
            ->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->reset(['foo' => 1], ['bar'], ProjectionStatus::RESETTING);
    }

    public function testDispatchEventOnProjectionDeleted(): void
    {
        $this->store->expects($this->once())->method('delete')->willReturn(true);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event): bool {
                $this->assertEquals(ProjectionDeleted::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);

                return true;
            }))->willReturn(true);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->delete();
    }

    public function testDispatchExceptionOnExceptionRaisedWhenDeletingProjection(): void
    {
        $this->expectException(RuntimeException::class);

        $exception = new RuntimeException('error');

        $this->store->expects($this->once())->method('delete')->willThrowException($exception);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($exception): bool {
                $this->assertEquals(ProjectionOnError::class, $event::class);
                $this->assertEquals('stream_name', $event->streamName);
                $this->assertEquals($exception, $event->exception);

                return true;
            }));

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $store->delete();
    }

    #[DataProvider('provideBoolean')]
    public function testAcquireLock($lockAcquired): void
    {
        $this->store->expects($this->once())->method('acquireLock')->willReturn($lockAcquired);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($lockAcquired, $store->acquireLock());
    }

    #[DataProvider('provideBoolean')]
    public function testReleaseLock($lockReleased): void
    {
        $this->store->expects($this->once())->method('releaseLock')->willReturn($lockReleased);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($lockReleased, $store->releaseLock());
    }

    #[DataProvider('provideBoolean')]
    public function testUpdateLock($lockUpdated): void
    {
        $this->store
            ->expects($this->once())
            ->method('updateLock')
            ->with(['foo' => 144])
            ->willReturn($lockUpdated);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($lockUpdated, $store->updateLock(['foo' => 144]));
    }

    public function testLoadProjectionStatus(): void
    {
        $this->store->expects($this->once())->method('loadStatus')->willReturn(ProjectionStatus::IDLE);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals(ProjectionStatus::IDLE, $store->loadStatus());
    }

    #[DataProvider('provideBoolean')]
    public function testPersistProjection(bool $isPersisted): void
    {
        $this->store
            ->expects($this->once())
            ->method('persist')
            ->with(['foo' => 144], ['bar'])
            ->willReturn($isPersisted);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($isPersisted, $store->persist(['foo' => 144], ['bar']));
    }

    #[DataProvider('provideBoolean')]
    public function testCheckStreamExists(bool $streamExists): void
    {
        $this->store->expects($this->once())->method('exists')->willReturn($streamExists);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals($streamExists, $store->exists());
    }

    public function testLoadProjectionState(): void
    {
        $this->store
            ->expects($this->once())
            ->method('loadState')
            ->willReturn([['foo' => 10], ['count' => 1]]);

        $store = new DispatcherAwareRepository($this->store, $this->eventDispatcher);

        $this->assertEquals([['foo' => 10], ['count' => 1]], $store->loadState());
    }

    public function testGetProjectionName(): void
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
