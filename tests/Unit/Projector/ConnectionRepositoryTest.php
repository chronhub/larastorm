<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Larastorm\Projection\ConnectionRepository;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Projector\ProjectionRepositoryInterface;
use Chronhub\Storm\Projector\Exceptions\ProjectionAlreadyRunning;
use Chronhub\Storm\Projector\ProjectionStatus;
use Generator;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;

#[CoversClass(ConnectionRepository::class)]
final class ConnectionRepositoryTest extends UnitTestCase
{
    private ProjectionRepositoryInterface|MockObject $store;

    private QueryException $queryException;

    public function setUp(): void
    {
        $this->store = $this->createMock(ProjectionRepositoryInterface::class);
        $this->queryException = new QueryException(
            'some_connection',
            'some_sql', [],
            new RuntimeException('foo')
        );
    }

    public function testCreate(): void
    {
        $this->store
            ->expects($this->once())
            ->method('create')
            ->with(ProjectionStatus::RUNNING)
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->create(ProjectionStatus::RUNNING));
    }

    public function testQueryFailureOnCreate(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('create')
            ->with(ProjectionStatus::RUNNING)
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->create(ProjectionStatus::RUNNING);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnCreate(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to create projection for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('create')
            ->with(ProjectionStatus::RUNNING)
            ->willReturn(false);

        $this->newRepository()->create(ProjectionStatus::RUNNING);
    }

    public function testStop(): void
    {
        $this->store
            ->expects($this->once())
            ->method('stop')
            ->with(['foo' => 10], ['count' => 0])
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->stop(['foo' => 10], ['count' => 0]));
    }

    public function testQueryFailureOnStop(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('stop')
            ->with(['foo' => 10], ['count' => 0])
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->stop(['foo' => 10], ['count' => 0]);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnStop(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to stop projection for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('stop')
            ->with(['foo' => 10], ['count' => 0])
            ->willReturn(false);

        $this->newRepository()->stop(['foo' => 10], ['count' => 0]);
    }

    public function testStartAgain(): void
    {
        $this->store
            ->expects($this->once())
            ->method('startAgain')
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->startAgain());
    }

    public function testQueryFailureOnStartAgain(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('startAgain')
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->startAgain();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnStartAgain(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to restart projection for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())->method('startAgain')->willReturn(false);

        $this->newRepository()->startAgain();
    }

    public function testPersist(): void
    {
        $this->store
            ->expects($this->once())
            ->method('persist')
            ->with(['foo' => 10], ['count' => 0])
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->persist(['foo' => 10], ['count' => 0]));
    }

    public function testQueryFailureOnPersist(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('persist')
            ->with(['foo' => 10], ['count' => 0])
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->persist(['foo' => 10], ['count' => 0]);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnPersist(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to persist projection for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('persist')
            ->with(['foo' => 10], ['count' => 0])
            ->willReturn(false);

        $this->newRepository()->persist(['foo' => 10], ['count' => 0]);
    }

    public function testReset(): void
    {
        $this->store
            ->expects($this->once())
            ->method('reset')
            ->with(['foo' => 10], ['count' => 0], ProjectionStatus::RESETTING)
            ->willReturn(true);

        $this->assertTrue(
            $this->newRepository()->reset(['foo' => 10], ['count' => 0], ProjectionStatus::RESETTING)
        );
    }

    public function testQueryFailureOnReset(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('reset')
            ->with(['foo' => 10], ['count' => 0], ProjectionStatus::RESETTING)
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->reset(['foo' => 10], ['count' => 0], ProjectionStatus::RESETTING);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnReset(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to reset projection for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('reset')
            ->with(['foo' => 10], ['count' => 0], ProjectionStatus::RESETTING)
            ->willReturn(false);

        $this->newRepository()->reset(['foo' => 10], ['count' => 0], ProjectionStatus::RESETTING);
    }

    public function testDelete(): void
    {
        $this->store
            ->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->delete());
    }

    public function testQueryFailureOnDelete(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('delete')
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->delete();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnDelete(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to delete projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('delete')
            ->willReturn(false);

        $this->newRepository()->delete();
    }

    public function testAcquireLock(): void
    {
        $this->store
            ->expects($this->once())
            ->method('acquireLock')
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->acquireLock());
    }

    public function testQueryFailureOnAcquireLock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('acquireLock')
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->acquireLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnAcquireLock(): void
    {
        $this->expectException(ProjectionAlreadyRunning::class);
        $this->expectExceptionMessage('Acquiring lock failed for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('acquireLock')
            ->willReturn(false);

        $this->newRepository()->acquireLock();
    }

    public function testUpdateLock(): void
    {
        $this->store
            ->expects($this->once())
            ->method('updateLock')
            ->with(['foo' => 100])
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->updateLock(['foo' => 100]));
    }

    public function testQueryFailureOnUpdateLock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('updateLock')
            ->with(['foo' => 100])
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->updateLock(['foo' => 100]);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnUpdateLock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection lock for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('updateLock')
            ->with(['foo' => 100])
            ->willReturn(false);

        $this->newRepository()->updateLock(['foo' => 100]);
    }

    public function testReleaseLock(): void
    {
        $this->store
            ->expects($this->once())
            ->method('releaseLock')
            ->willReturn(true);

        $this->assertTrue($this->newRepository()->releaseLock());
    }

    public function testQueryFailureOnReleaseLock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store
            ->expects($this->once())
            ->method('releaseLock')
            ->willThrowException($this->queryException);

        try {
            $this->newRepository()->releaseLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    public function testProjectionFailedOnReleaseLock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to release projection lock for stream name: some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('some_stream_name');

        $this->store
            ->expects($this->once())
            ->method('releaseLock')
            ->willReturn(false);

        $connectionProvider = $this->newRepository();

        $connectionProvider->releaseLock();
    }

    public function testLoadStatus(): void
    {
        $this->store
            ->expects($this->once())
            ->method('loadStatus')
            ->willReturn(ProjectionStatus::RUNNING);

        $this->assertEquals(ProjectionStatus::RUNNING, $this->newRepository()->loadStatus());
    }

    public function testLoadState(): void
    {
        $this->store
            ->expects($this->once())
            ->method('loadState')
            ->willReturn([['foo' => 100], ['count' => 125]]);

        $this->assertEquals(
            [['foo' => 100], ['count' => 125]],
            ($this->newRepository())->loadState()
        );
    }

    #[DataProvider('provideBoolean')]
    public function testProjectionExists(bool $projectionExists): void
    {
        $this->store
            ->expects($this->once())
            ->method('exists')
            ->willReturn($projectionExists);

        $this->assertEquals($projectionExists, $this->newRepository()->exists());
    }

    public function testGetProjectionName(): void
    {
        $this->store
            ->expects($this->once())
            ->method('projectionName')
            ->willReturn('foo');

        $this->assertEquals('foo', $this->newRepository()->projectionName());
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }

    private function newRepository(): ConnectionRepository
    {
        return new ConnectionRepository($this->store);
    }
}
