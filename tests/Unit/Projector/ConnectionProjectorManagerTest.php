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
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Storm\Projector\ProjectReadModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Storm\Contracts\Clock\SystemClock;
use Chronhub\Storm\Projector\ProjectProjection;
use Chronhub\Storm\Contracts\Projector\ReadModel;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\QueryProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Storm\Contracts\Serializer\JsonSerializer;
use Chronhub\Storm\Contracts\Projector\ProjectionOption;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;
use Chronhub\Storm\Contracts\Projector\ReadModelProjector;
use Chronhub\Storm\Contracts\Projector\ProjectionProjector;
use Chronhub\Storm\Projector\Exceptions\ProjectionNotFound;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionQueryScope;
use Chronhub\Larastorm\Exceptions\ConnectionProjectionFailed;
use Chronhub\Larastorm\Projection\ConnectionProjectorManager;
use Chronhub\Storm\Projector\Options\DefaultProjectionOption;

#[CoversClass(ConnectionProjectorManager::class)]
final class ConnectionProjectorManagerTest extends UnitTestCase
{
    public function testQueryProjection(): void
    {
        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $projector = $manager->projectQuery();

        $this->assertInstanceOf(QueryProjector::class, $projector);
        $this->assertEquals(ProjectQuery::class, $projector::class);
    }

    public function testPersistentProjection(): void
    {
        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $projector = $manager->projectProjection('balance');

        $this->assertInstanceOf(ProjectionProjector::class, $projector);
        $this->assertEquals(ProjectProjection::class, $projector::class);
        $this->assertEquals('balance', $projector->getStreamName());
    }

    public function testReadModelProjection(): void
    {
        $readModel = $this->createMock(ReadModel::class);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $projector = $manager->projectReadModel('balance', $readModel);

        $this->assertInstanceOf(ReadModelProjector::class, $projector);
        $this->assertEquals(ProjectReadModel::class, $projector::class);
        $this->assertEquals('balance', $projector->getStreamName());
        $this->assertSame($readModel, $projector->readModel());
    }

    public function testFetchStatusOfProjection(): void
    {
        $model = $this->createMock(ProjectionModel::class);

        $model->expects($this->once())
            ->method('status')
            ->willReturn('running');

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn($model);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $status = $manager->statusOf('balance');

        $this->assertEquals('running', $status);
    }

    public function testExceptionRaisedWhenRetrieveStatusOfProjectionNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn(null);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->statusOf('balance');
    }

    public function testFetchStreamPositionOfProjection(): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $position = $manager->streamPositionsOf('balance');

        $this->assertEquals(['balance' => 5], $position);
    }

    public function testReturnEmptyStreamPosition(): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $streamPosition = $manager->streamPositionsOf('balance');

        $this->assertEquals([], $streamPosition);
    }

    public function testExceptionRaisedWhenRetrieveStreamPositionOfProjectionNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn(null);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->streamPositionsOf('balance');
    }

    public function testFetchStateOfProjection(): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $position = $manager->stateOf('balance');

        $this->assertEquals(['count' => 10], $position);
    }

    #[Test]
    public function testFetchEmptyState(): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $position = $manager->stateOf('balance');

        $this->assertEquals([], $position);
    }

    #[Test]
    public function testExceptionRaisedWhenRetrieveStateOfProjectionNotFound(): void
    {
        $this->expectException(ProjectionNotFound::class);

        $this->projectionProvider->expects($this->once())
            ->method('retrieve')
            ->with('balance')
            ->willReturn(null);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->stateOf('balance');
    }

    public function testFilterProjectionNames(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('filterByNames')
            ->with('balance', 'foo', 'bar')
            ->willReturn(['balance']);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $streamNames = $manager->filterNamesByAscendantOrder('balance', 'foo', 'bar');

        $this->assertEquals(['balance'], $streamNames);
    }

    #[DataProvider('provideBoolean')]
    public function testStreamExists(bool $exists): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('projectionExists')
            ->with('balance')
            ->willReturn($exists);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $this->assertEquals($exists, $manager->exists('balance'));
    }

    #[Test]
    public function testQueryScopeGetter(): void
    {
        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $this->assertEquals($this->queryScope, $manager->queryScope());
    }

    #[Test]
    public function testMarkProjectionStopped(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willReturn(true);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->stop('balance');
    }

    #[Test]
    public function testExceptionRaisedWhenMarkStoppedOfProjectionNotFound(): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->stop('balance');
    }

    public function testExceptionRaisedWhenUpdateProjectionFailed(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willThrowException(new QueryException('', '', [], new RuntimeException('nope')));

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->stop('balance');
    }

    public function testExceptionRaisedWhenUpdateProjectionFailed_2(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status stopping');

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::STOPPING->value,
            ])
            ->willThrowException(new RuntimeException('nope'));

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->stop('balance');
    }

    public function testMarkProjectionReset(): void
    {
        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willReturn(true);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->reset('balance');
    }

    public function testExceptionRaisedWhenMarkResetOnProjectionNotFound(): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->reset('balance');
    }

    public function testExceptionRaisedWhenMarkResetProjectionFailed(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willThrowException(new QueryException('', '', [], new RuntimeException('nope')));

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->reset('balance');
    }

    #[Test]
    public function testExceptionRaisedWhenMarkResetProjectionFailed_2(): void
    {
        $this->expectException(ConnectionProjectionFailed::class);
        $this->expectExceptionMessage('Unable to update projection status for stream name balance and status resetting');

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => ProjectionStatus::RESETTING->value,
            ])
            ->willThrowException(new RuntimeException('nope'));

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->reset('balance');
    }

    #[DataProvider('provideBoolean')]
    public function testMarkDeletingProjection(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => $status,
            ])
            ->willReturn(true);

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    #[DataProvider('provideBoolean')]
    public function testExceptionRaisedWhenMarkDeletingOnProjectionNotFound(bool $withEmittedEvents): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function testExceptionRaisedWhenMarkDeletingFailed(bool $withEmittedEvents): void
    {
        $status = $withEmittedEvents ? ProjectionStatus::DELETING_WITH_EMITTED_EVENTS->value : ProjectionStatus::DELETING->value;

        $this->expectException(ConnectionProjectionFailed::class);

        $this->projectionProvider->expects($this->once())
            ->method('updateProjection')
            ->with('balance', [
                'status' => $status,
            ])
            ->willThrowException(new QueryException('', '', [], new RuntimeException('nope')));

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

        $manager->delete('balance', $withEmittedEvents);
    }

    #[DataProvider('provideBoolean')]
    #[Test]
    public function testExceptionRaisedWhenMarkDeletingFailed_2(bool $withEmittedEvents): void
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

        $manager = $this->newProjectorManager(new DefaultProjectionOption());

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

    private ProjectionOption|MockObject $projectionOption;

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
        $this->projectionOption = $this->createMock(ProjectionOption::class);
    }

    private function newProjectorManager(array|ProjectionOption $projectorOption = null): ConnectionProjectorManager
    {
        return new ConnectionProjectorManager(
            $this->chronicler,
            $this->eventStreamProvider,
            $this->projectionProvider,
            $this->queryScope,
            $this->clock,
            $this->jsonSerializer,
            $projectorOption ?? $this->projectionOption,
        );
    }
}
