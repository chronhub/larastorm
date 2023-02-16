<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Generator;
use RuntimeException;
use Prophecy\Prophecy\ObjectProphecy;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Projector\ProjectQuery;
use Chronhub\Larastorm\Tests\ProphecyTestCase;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Storm\Projector\ProjectReadModel;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Projector\ProjectProjection;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\QueryProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ReadModelProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionProjector;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Larastorm\Projection\ConnectionProjectorFactory;
use Chronhub\Larastorm\Projection\ConnectionProjectorManager;

final class ConnectionProjectorManagerTest extends ProphecyTestCase
{
    /**
     * @test
     */
    public function it_create_query_projection(): void
    {
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $projector = $manager->projectQuery();

        $this->assertInstanceOf(QueryProjector::class, $projector);
        $this->assertEquals(ProjectQuery::class, $projector::class);
    }

    /**
     * @test
     */
    public function it_create_persistent_projection(): void
    {
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $projector = $manager->projectProjection('balance');

        $this->assertInstanceOf(ProjectionProjector::class, $projector);
        $this->assertEquals(ProjectProjection::class, $projector::class);
        $this->assertEquals('balance', $projector->getStreamName());
    }

    /**
     * @test
     */
    public function it_create_read_model_projection(): void
    {
        $readModel = $this->prophesize(ReadModel::class)->reveal();

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $projector = $manager->projectReadModel('balance', $readModel);

        $this->assertInstanceOf(ReadModelProjector::class, $projector);
        $this->assertEquals(ProjectReadModel::class, $projector::class);
        $this->assertEquals('balance', $projector->getStreamName());
        $this->assertSame($readModel, $projector->readModel());
    }

    /**
     * @test
     */
    public function it_fetch_status_of_stream(): void
    {
        $model = $this->prophesize(ProjectionModel::class);
        $model->status()->willReturn('running')->shouldBeCalledOnce();

        $this->projectionProvider->retrieve('balance')->willReturn($model)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $status = $manager->statusOf('balance');

        $this->assertEquals('running', $status);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_stream_on_status_not_found(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->retrieve('balance')->willReturn(null)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->statusOf('balance');
    }

    /**
     * @test
     */
    public function it_fetch_stream_positions_of_stream(): void
    {
        $model = $this->prophesize(ProjectionModel::class);
        $model->position()->willReturn('{"balance":5}')->shouldBeCalledOnce();

        $this->projectionProvider->retrieve('balance')->willReturn($model)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $position = $manager->streamPositionsOf('balance');

        $this->assertEquals(['balance' => 5], $position);
    }

    /**
     * @test
     */
    public function it_fetch_empty_stream_positions_of_stream_and_return_empty_array(): void
    {
        $model = $this->prophesize(ProjectionModel::class);
        $model->position()->willReturn('{}')->shouldBeCalledOnce();

        $this->projectionProvider->retrieve('balance')->willReturn($model)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $position = $manager->streamPositionsOf('balance');

        $this->assertEquals([], $position);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_stream_not_found_call_stream_positions(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->retrieve('balance')->willReturn(null)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->streamPositionsOf('balance');
    }

    /**
     * @test
     */
    public function it_fetch_state_of_stream(): void
    {
        $model = $this->prophesize(ProjectionModel::class);
        $model->state()->willReturn('{"count":10}')->shouldBeCalledOnce();

        $this->projectionProvider->retrieve('balance')->willReturn($model)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $position = $manager->stateOf('balance');

        $this->assertEquals(['count' => 10], $position);
    }

    /**
     * @test
     */
    public function it_fetch_empty_state_of_stream_and_return_empty_array(): void
    {
        $model = $this->prophesize(ProjectionModel::class);
        $model->state()->willReturn('{}')->shouldBeCalledOnce();

        $this->projectionProvider->retrieve('balance')->willReturn($model)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $position = $manager->stateOf('balance');

        $this->assertEquals([], $position);
    }

    /**
     * @test
     */
    public function it_raise_exception_when_stream_on_state_not_found(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->retrieve('balance')->willReturn(null)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->stateOf('balance');
    }

    /**
     * @test
     */
    public function it_filter_stream_by_names(): void
    {
        $this->projectionProvider->filterByNames('balance', 'foo', 'bar')->willReturn(['balance'])->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $streamNames = $manager->filterNamesOf('balance', 'foo', 'bar');

        $this->assertEquals(['balance'], $streamNames);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_assert_stream_exists(bool $exists): void
    {
        $this->projectionProvider->projectionExists('balance')->willReturn($exists)->shouldBeCalledOnce();
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $this->assertEquals($exists, $manager->exists('balance'));
    }

    /**
     * @test
     */
    public function it_access_query_scope(): void
    {
        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $this->assertEquals($this->queryScope->reveal(), $manager->queryScope());
    }

    /**
     * @test
     */
    public function it_mark_projection_as_stopped(): void
    {
        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::STOPPING->value,
        ])->shouldBeCalledOnce()->willReturn(true);

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->stop('balance');
    }

    /**
     * @test
     */
    public function it_raise_exception_on_stopping_not_found_stream(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->projectionExists('balance')->willReturn(false)->shouldBeCalledOnce();

        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::STOPPING->value,
        ])->shouldBeCalledOnce()->willReturn(false);

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->stop('balance');
    }

    /**
     * @test
     */
    public function it_raise_exception_on_stopping_when_query_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::STOPPING->value,
        ])->willThrow(new QueryException('', '', [], new RuntimeException('nope')));

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->stop('balance');
    }

    /**
     * @test
     */
    public function it_raise_exception_on_stopping_when_throwable_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status stopping');

        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::STOPPING->value,
        ])->willThrow(new RuntimeException('nope'));

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->stop('balance');
    }

    /**
     * @test
     */
    public function it_mark_projection_as_resetting(): void
    {
        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::RESETTING->value,
        ])->shouldBeCalledOnce()->willReturn(true);

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->reset('balance');
    }

    /**
     * @test
     */
    public function it_raise_exception_on_resetting_not_found_stream(): void
    {
        $this->projectionProvider->projectionExists('balance')->willReturn(false)->shouldBeCalledOnce();

        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::RESETTING->value,
        ])->shouldBeCalledOnce()->willReturn(false);

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->reset('balance');
    }

    /**
     * @test
     */
    public function it_raise_exception_on_resetting_when_query_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::RESETTING->value,
        ])->willThrow(new QueryException('', '', [], new RuntimeException('nope')));

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->reset('balance');
    }

    /**
     * @test
     */
    public function it_raise_exception_on_resetting_when_throwable_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status resetting');

        $this->projectionProvider->updateProjection('balance', [
            'status' => ProjectionStatus::RESETTING->value,
        ])->willThrow(new RuntimeException('nope'));

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->reset('balance');
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_mark_projection_as_deleting(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->projectionProvider->updateProjection('balance', [
            'status' => $status,
        ])->shouldBeCalledOnce()->willReturn(true);

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->delete('balance', $withEmittedEvents);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_raise_exception_on_deleting_not_found_stream(bool $withEmittedEvents): void
    {
        $this->projectionProvider->projectionExists('balance')->willReturn(false)->shouldBeCalledOnce();

        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->updateProjection('balance', [
            'status' => $status,
        ])->shouldBeCalledOnce()->willReturn(false);

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->delete('balance', $withEmittedEvents);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_raise_exception_on_deleting_when_query_exception_raised(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->updateProjection('balance', [
            'status' => $status,
        ])->willThrow(new QueryException('', '', [], new RuntimeException('nope')));

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->delete('balance', $withEmittedEvents);
    }

    /**
     * @test
     *
     * @dataProvider provideBoolean
     */
    public function it_raise_exception_on_deleting_when_exception_raised(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status '.$status);

        $this->projectionProvider->updateProjection('balance', [
            'status' => $status,
        ])->willThrow(new RuntimeException('nope'));

        $factory = $this->managerFactoryInstance();

        $manager = new ConnectionProjectorManager($factory);

        $manager->delete('balance', $withEmittedEvents);
    }

    public function provideBoolean(): Generator
    {
        yield[true];
        yield[false];
    }

    private Chronicler|ObjectProphecy $chronicler;

    private EventStreamProvider|ObjectProphecy $eventStreamProvider;

    private ProjectionProvider|ObjectProphecy $projectionProvider;

    private ProjectionQueryScope|ObjectProphecy $queryScope;

    private SystemClock|ObjectProphecy $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chronicler = $this->prophesize(Chronicler::class);
        $this->eventStreamProvider = $this->prophesize(EventStreamProvider::class);
        $this->projectionProvider = $this->prophesize(ProjectionProvider::class);
        $this->queryScope = $this->prophesize(ProjectionQueryScope::class);
        $this->clock = $this->prophesize(SystemClock::class);
    }

    private function managerFactoryInstance(): ConnectionProjectorFactory
    {
        return new ConnectionProjectorFactory(
            $this->chronicler->reveal(),
            $this->eventStreamProvider->reveal(),
            $this->projectionProvider->reveal(),
            $this->queryScope->reveal(),
            $this->clock->reveal(),
            []
        );
    }
}
