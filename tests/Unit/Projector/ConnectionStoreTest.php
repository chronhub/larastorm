<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Generator;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Chronhub\Storm\Contracts\Projector\Store;
use Chronhub\Storm\Projector\ProjectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Projection\ConnectionStore;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Storm\Projector\Exceptions\ProjectionAlreadyRunning;

final class ConnectionStoreTest extends UnitTestCase
{
    private Store|MockObject $store;

    private QueryException $queryException;

    public function setUp(): void
    {
        $this->store = $this->createMock(Store::class);
        $this->queryException = new QueryException('some_connection', 'some_sql', [], new RuntimeException('foo'));
    }

    #[Test]
    public function it_create(): void
    {
        $this->store->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->create());
    }

    #[Test]
    public function it_raise_query_failure_on_create(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('create')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->create();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_created(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to create projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())
            ->method('create')
            ->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->create();
    }

    #[Test]
    public function it_stop(): void
    {
        $this->store->expects($this->once())
            ->method('stop')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->stop());
    }

    #[Test]
    public function it_raise_query_failure_on_stop(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('stop')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->stop();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_stopped(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to stop projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())->method('stop')->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->stop();
    }

    /***/
    #[Test]
    public function it_start_again(): void
    {
        $this->store->expects($this->once())
            ->method('startAgain')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->startAgain());
    }

    #[Test]
    public function it_raise_query_failure_on_start_again(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('startAgain')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->startAgain();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_start_again(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to restart projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())->method('startAgain')->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->startAgain();
    }

    /***/
    #[Test]
    public function it_persist(): void
    {
        $this->store->expects($this->once())
            ->method('persist')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->persist());
    }

    #[Test]
    public function it_raise_query_failure_on_persist(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('persist')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->persist();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_persist(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to persist projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())->method('persist')->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->persist();
    }

    /***/
    #[Test]
    public function it_reset(): void
    {
        $this->store->expects($this->once())
            ->method('reset')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->reset());
    }

    #[Test]
    public function it_raise_query_failure_on_reset(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('reset')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->reset();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_reset(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to reset projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())->method('reset')->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->reset();
    }

    /***/
    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_delete(bool $withEmittedEvents): void
    {
        $this->store->expects($this->once())
            ->method('delete')
            ->with($withEmittedEvents)
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->delete($withEmittedEvents));
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_raise_query_failure_on_delete(bool $withEmittedEvents): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('delete')
            ->with($withEmittedEvents)
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->delete($withEmittedEvents);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_raise_projection_failed_on_failed_delete(bool $withEmittedEvents): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to delete projection for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())
            ->method('delete')
            ->with($withEmittedEvents)
            ->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->delete($withEmittedEvents);
    }

    #[Test]
    public function it_acquire_lock(): void
    {
        $this->store->expects($this->once())
            ->method('acquireLock')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->acquireLock());
    }

    #[Test]
    public function it_raise_query_failure_on_acquire_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('acquireLock')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->acquireLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_acquire_lock(): void
    {
        $this->expectException(ProjectionAlreadyRunning::class);
        $this->expectExceptionMessage('Acquiring lock failed for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())
            ->method('acquireLock')
            ->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->acquireLock();
    }

    #[Test]
    public function it_update_lock(): void
    {
        $this->store->expects($this->once())
            ->method('updateLock')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->updateLock());
    }

    #[Test]
    public function it_raise_query_failure_on_update_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('updateLock')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->updateLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_update_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection lock for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())
            ->method('updateLock')
            ->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->updateLock();
    }

    #[Test]
    public function it_release_lock(): void
    {
        $this->store->expects($this->once())
            ->method('releaseLock')
            ->willReturn(true);

        $connectionProvider = new ConnectionStore($this->store);

        $this->assertTrue($connectionProvider->releaseLock());
    }

    #[Test]
    public function it_raise_query_failure_on_release_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->expects($this->once())
            ->method('releaseLock')
            ->willThrowException($this->queryException);

        $connectionProvider = new ConnectionStore($this->store);

        try {
            $connectionProvider->releaseLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    #[Test]
    public function it_raise_projection_failed_on_failed_release_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to release projection lock for stream name: some_stream_name');

        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('some_stream_name');

        $this->store->expects($this->once())
            ->method('releaseLock')
            ->willReturn(false);

        $connectionProvider = new ConnectionStore($this->store);

        $connectionProvider->releaseLock();
    }

    #[Test]
    public function it_load_status(): void
    {
        $this->store->expects($this->once())
            ->method('loadStatus')
            ->willReturn(ProjectionStatus::RUNNING);

        $this->assertEquals(ProjectionStatus::RUNNING, (new ConnectionStore($this->store))->loadStatus());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_load_state(bool $success): void
    {
        $this->store->expects($this->once())
            ->method('loadState')
            ->willReturn($success);

        $this->assertEquals($success, (new ConnectionStore($this->store))->loadState());
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function it_assert_projection_exists(bool $projectionExists): void
    {
        $this->store->expects($this->once())
            ->method('exists')
            ->willReturn($projectionExists);

        $this->assertEquals($projectionExists, (new ConnectionStore($this->store))->exists());
    }

    #[Test]
    public function it_access_current_stream_name(): void
    {
        $this->store->expects($this->once())
            ->method('currentStreamName')
            ->willReturn('foo');

        $this->assertEquals('foo', (new ConnectionStore($this->store))->currentStreamName());
    }

    public static function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
