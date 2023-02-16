<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Generator;
use RuntimeException;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Contracts\Projector\Store;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Larastorm\Projection\ConnectionStore;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Storm\Projector\Exceptions\ProjectionAlreadyRunning;

final class ConnectionStoreTest extends ProphecyTestCase
{
    private Store|ObjectProphecy $store;

    private QueryException $queryException;

    public function setUp(): void
    {
        $this->store = $this->prophesize(Store::class);
        $this->queryException = new QueryException('some_connection', 'some_sql', [], new RuntimeException('foo'));
    }

    /**
     * @test
     */
    public function it_create(): void
    {
        $this->store->create()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->create());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_create(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->create()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->create();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_created(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to create projection for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->create()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->create();
    }

    /***/

    /**
     * @test
     */
    public function it_stop(): void
    {
        $this->store->stop()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->stop());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_stop(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->stop()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->stop();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_stopped(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to stop projection for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->stop()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->stop();
    }

    /***/

    /**
     * @test
     */
    public function it_start_again(): void
    {
        $this->store->startAgain()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->startAgain());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_start_again(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->startAgain()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->startAgain();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_start_again(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to restart projection for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->startAgain()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->startAgain();
    }

    /***/

    /**
     * @test
     */
    public function it_persist(): void
    {
        $this->store->persist()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->persist());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_persist(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->persist()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->persist();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_persist(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to persist projection for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->persist()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->persist();
    }

    /***/

    /**
     * @test
     */
    public function it_reset(): void
    {
        $this->store->reset()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->reset());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_reset(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->reset()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->reset();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_reset(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to reset projection for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->reset()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->reset();
    }

    /***/

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_delete(bool $withEmittedEvents): void
    {
        $this->store->delete($withEmittedEvents)->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->delete($withEmittedEvents));
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_raise_query_failure_on_delete(bool $withEmittedEvents): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->delete($withEmittedEvents)->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->delete($withEmittedEvents);
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_raise_projection_failed_on_failed_delete(bool $withEmittedEvents): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to delete projection for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->delete($withEmittedEvents)->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->delete($withEmittedEvents);
    }

    /**
     * @test
     */
    public function it_acquire_lock(): void
    {
        $this->store->acquireLock()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->acquireLock());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_acquire_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->acquireLock()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->acquireLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_acquire_lock(): void
    {
        $this->expectException(ProjectionAlreadyRunning::class);
        $this->expectExceptionMessage('Acquiring lock failed for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->acquireLock()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->acquireLock();
    }

    /**
     * @test
     */
    public function it_update_lock(): void
    {
        $this->store->updateLock()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->updateLock());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_update_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->updateLock()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->updateLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_update_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection lock for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->updateLock()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->updateLock();
    }

    /**
     * @test
     */
    public function it_release_lock(): void
    {
        $this->store->releaseLock()->willReturn(true)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $this->assertTrue($connectionProvider->releaseLock());
    }

    /**
     * @test
     */
    public function it_raise_query_failure_on_release_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->store->releaseLock()->willThrow($this->queryException)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        try {
            $connectionProvider->releaseLock();
        } catch (ConnectionProjectionFailed $e) {
            $this->assertEquals($this->queryException, $e->getPrevious());

            throw $e;
        }
    }

    /**
     * @test
     */
    public function it_raise_projection_failed_on_failed_release_lock(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to release projection lock for stream name: some_stream_name');

        $this->store->currentStreamName()->willReturn('some_stream_name')->shouldBeCalledOnce();
        $this->store->releaseLock()->willReturn(false)->shouldBeCalledOnce();

        $connectionProvider = new ConnectionStore($this->store->reveal());

        $connectionProvider->releaseLock();
    }

    /**
     * @test
     */
    public function it_load_status(): void
    {
        $this->store->loadStatus()->willReturn(ProjectionStatus::RUNNING)->shouldBeCalledOnce();

        $this->assertEquals(ProjectionStatus::RUNNING, (new ConnectionStore($this->store->reveal()))->loadStatus());
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_load_state(bool $success): void
    {
        $this->store->loadState()->willReturn($success)->shouldBeCalledOnce();

        $this->assertEquals($success, (new ConnectionStore($this->store->reveal()))->loadState());
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_assert_projection_exists(bool $projectionExists): void
    {
        $this->store->exists()->willReturn($projectionExists)->shouldBeCalledOnce();

        $this->assertEquals($projectionExists, (new ConnectionStore($this->store->reveal()))->exists());
    }

    /**
     * @test
     */
    public function it_access_current_stream_name(): void
    {
        $this->store->currentStreamName()->willReturn('foo')->shouldBeCalledOnce();

        $this->assertEquals('foo', (new ConnectionStore($this->store->reveal()))->currentStreamName());
    }

    public function provideBoolean(): Generator
    {
        yield [true];
        yield [false];
    }
}
