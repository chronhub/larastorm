<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Unit\Projector;

use Generator;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Projector\ProjectQuery;
use PHPUnit\Framework\MockObject\MockObject;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Storm\Projector\ProjectReadModel;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Projector\ProjectProjection;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\QueryProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Storm\Contracts\Projector\ProjectorOption;
use Chronhub\Storm\Contracts\Serializer\JsonSerializer;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ReadModelProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionProjector;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;
use Chronhub\Storm\Projector\Options\DefaultProjectorOption;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Larastorm\Projection\ConnectionProjectorManager;

/**
 * @coversDefaultClass \Chronhub\Larastorm\Projection\ConnectionProjectorManager
 */
final class ConnectionProjectorManagerTest extends UnitTestCase
{
    #[Test]
    public function it_create_query_projection(): void
    {
        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $projector = $manager->projectQuery();

        $this->assertInstanceOf(QueryProjector::class, $projector);
        $this->assertEquals(ProjectQuery::class, $projector::class);
    }

    #[Test]
    public function it_create_persistent_projection(): void
    {
        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $projector = $manager->projectProjection('balance');

        $this->assertInstanceOf(ProjectionProjector::class, $projector);
        $this->assertEquals(ProjectProjection::class, $projector::class);
        $this->assertEquals('balance', $projector->getStreamName());
    }

    #[Test]
    public function it_create_read_model_projection(): void
    {
        $readModel = $this->createMock(ReadModel::class);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $projector = $manager->projectReadModel('balance', $readModel);

        $this->assertInstanceOf(ReadModelProjector::class, $projector);
        $this->assertEquals(ProjectReadModel::class, $projector::class);
        $this->assertEquals('balance', $projector->getStreamName());
        $this->assertSame($readModel, $projector->readModel());
    }

    #[Test]
    public function it_fetch_status_of_stream(): void
    {
        $model = $this->createMock(ProjectionModel::class);

        $model->expects($this->once())
            ->method('status')
            ->willReturn('running');

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn($model);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $status = $manager->statusOf('balance');

        $this->assertEquals('running', $status);
    }

    #[Test]
    public function it_raise_exception_when_stream_on_status_not_found(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn(null);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->statusOf('balance');
    }

    #[Test]
    public function it_fetch_stream_positions_of_stream(): void
    {
        $model = $this->createMock(ProjectionModel::class);

        $model->expects($this->once())
            ->method('position')
            ->willReturn('{"balance":5}');

        $this->jsonSerializer->expects($this->once())
            ->method('decode')
            ->with('{"balance":5}')
            ->willReturn(['balance' => 5]);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn($model);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $position = $manager->streamPositionsOf('balance');

        $this->assertEquals(['balance' => 5], $position);
    }

    #[Test]
    public function it_fetch_empty_stream_positions_of_stream_and_return_empty_array(): void
    {
        $model = $this->createMock(ProjectionModel::class);
        $model->expects($this->once())
            ->method('position')
            ->willReturn('{}');

        $this->jsonSerializer->expects($this->once())
            ->method('decode')
            ->with('{}')
            ->willReturn([]);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn($model);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $streamPosition = $manager->streamPositionsOf('balance');

        $this->assertEquals([], $streamPosition);
    }

    #[Test]
    public function it_raise_exception_when_stream_not_found_call_stream_positions(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn(null);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->streamPositionsOf('balance');
    }

    #[Test]
    public function it_fetch_state_of_stream(): void
    {
        $model = $this->createMock(ProjectionModel::class);
        $model->expects($this->once())
            ->method('state')
            ->willReturn('{"count":10}');

        $this->jsonSerializer->expects($this->once())
            ->method('decode')
            ->with('{"count":10}')
            ->willReturn(['count' => 10]);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn($model);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $position = $manager->stateOf('balance');

        $this->assertEquals(['count' => 10], $position);
    }

    #[Test]
    public function it_fetch_empty_state_of_stream_and_return_empty_array(): void
    {
        $model = $this->createMock(ProjectionModel::class);
        $model->expects($this->once())
            ->method('state')
            ->willReturn('{}');

        $this->jsonSerializer->expects($this->once())
            ->method('decode')
            ->with('{}')
            ->willReturn([]);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn($model);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $position = $manager->stateOf('balance');

        $this->assertEquals([], $position);
    }

    #[Test]
    public function it_raise_exception_when_stream_on_state_not_found(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn(null);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->stateOf('balance');
    }

    #[Test]
    public function it_filter_stream_by_names(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('filterByNames')
            ->with('balance', 'foo', 'bar')
            ->willReturn(['balance']);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $streamNames = $manager->filterNamesByAscendantOrder('balance', 'foo', 'bar');

        $this->assertEquals(['balance'], $streamNames);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_assert_stream_exists(bool $exists): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('projectionExists')
            ->with('balance')
            ->willReturn($exists);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $this->assertEquals($exists, $manager->exists('balance'));
    }

    #[Test]
    public function it_access_query_scope(): void
    {
        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $this->assertEquals($this->queryScope, $manager->queryScope());
    }

    #[Test]
    public function it_mark_projection_as_stopped(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willReturn(true);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->stop('balance');
    }

    #[Test]
    public function it_raise_exception_on_stopping_not_found_stream(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('projectionExists')
            ->with('balance')
            ->willReturn(false);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willReturn(false);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->stop('balance');
    }

    #[Test]
    public function it_raise_exception_on_stopping_when_query_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willThrowException(new QueryException('', '', [], new RuntimeException('nope')));

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->stop('balance');
    }

    #[Test]
    public function it_raise_exception_on_stopping_when_throwable_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status stopping');

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willThrowException(new RuntimeException('nope'));

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->stop('balance');
    }

    #[Test]
    public function it_mark_projection_as_resetting(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willReturn(true);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->reset('balance');
    }

    #[Test]
    public function it_raise_exception_on_resetting_not_found_stream(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('projectionExists')
            ->with('balance')
            ->willReturn(false);

        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willReturn(false);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->reset('balance');
    }

    #[Test]
    public function it_raise_exception_on_resetting_when_query_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willThrowException(new QueryException('', '', [], new RuntimeException('nope')));

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->reset('balance');
    }

    #[Test]
    public function it_raise_exception_on_resetting_when_throwable_exception_raised(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status resetting');

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willThrowException(new RuntimeException('nope'));

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->reset('balance');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_mark_projection_as_deleting(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => $status,
            ])
            ->willReturn(true);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_raise_exception_on_deleting_not_found_stream(bool $withEmittedEvents): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('projectionExists')
            ->with('balance')
            ->willReturn(false);

        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => $status,
            ])
            ->willReturn(false);

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_raise_exception_on_deleting_when_query_exception_raised(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => $status,
            ])
            ->willThrowException(new QueryException('', '', [], new RuntimeException('nope')));

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('provideBoolean')]
    #[Test]
    public function it_raise_exception_on_deleting_when_exception_raised(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status '.$status);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => $status,
            ])
            ->willThrowException(new RuntimeException('nope'));

        $manager = $this->newProjectorManager(new DefaultProjectorOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    public static function provideBoolean(): Generator
    {
        yield[true];
        yield[false];
    }

    private Chronicler|MockObject $chronicler;

    private EventStreamProvider|MockObject $eventStreamProvider;

    private ProjectionProvider|MockObject $projectionProvider;

    private ProjectionQueryScope|MockObject $queryScope;

    private SystemClock|MockObject $clock;

    private ProjectorOption|MockObject $projectorOption;

    private JsonSerializer|MockObject $jsonSerializer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chronicler = $this->createMock(Chronicler::class);
        $this->eventStreamProvider = $this->createMock(EventStreamProvider::class);
        $this->projectionProvider = $this->createMock(ProjectionProvider::class);
        $this->queryScope = $this->createMock(ProjectionQueryScope::class);
        $this->clock = $this->createMock(SystemClock::class);
        $this->jsonSerializer = $this->createMock(JsonSerializer::class);
        $this->projectorOption = $this->createMock(ProjectorOption::class);
    }

    private function newProjectorManager(array|ProjectorOption $projectorOption = null): ConnectionProjectorManager
    {
        return new ConnectionProjectorManager(
            $this->chronicler,
            $this->eventStreamProvider,
            $this->projectionProvider,
            $this->queryScope,
            $this->clock,
            $this->jsonSerializer,
            $projectorOption ?? $this->projectorOption,
        );
    }
}
