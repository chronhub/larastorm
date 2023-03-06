<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use DateInterval;
use DateTimeZone;
use DateTimeImmutable;
use Chronhub\Storm\Clock\PointInTime;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use Chronhub\Larastorm\Projection\Projection;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider;

#[CoversClass(Projection::class)]
final class ProjectionTest extends OrchestraTestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_assert_projection(): void
    {
        $projection = new Projection();

        $this->assertInstanceOf(ProjectionModel::class, $projection);
        $this->assertInstanceOf(ProjectionProvider::class, $projection);
        $this->assertNull($this->findFirstProjection());
    }

    #[Test]
    public function it_create_projection(): void
    {
        $projection = new Projection();

        $created = $projection->createProjection('balance', ProjectionStatus::IDLE->value);

        $this->assertTrue($created);

        /** @var Projection $model */
        $model = $projection->newQuery()->find(1);

        $this->assertEquals([
            'no' => 1,
            'name' => 'balance',
            'position' => '{}',
            'state' => '{}',
            'status' => 'idle',
            'locked_until' => null,
        ], $model->toArray());

        $this->assertEquals('balance', $model->name());
        $this->assertEquals('{}', $model->position());
        $this->assertEquals('{}', $model->state());
        $this->assertEquals('idle', $model->status());
        $this->assertNull($model->lockedUntil());
    }

    #[Test]
    public function it_update_projection_by_projection_name(): void
    {
        $projection = new Projection();

        $created = $projection->createProjection('balance', ProjectionStatus::IDLE->value);
        $this->assertTrue($created);

        /** @var Projection $model */
        $model = $projection->newQuery()->find(1);

        $this->assertEquals([
            'no' => 1,
            'name' => 'balance',
            'position' => '{}',
            'state' => '{}',
            'status' => 'idle',
            'locked_until' => null,
        ], $model->toArray());

        $this->assertEquals('balance', $model->name());
        $this->assertEquals('{}', $model->position());
        $this->assertEquals('{}', $model->state());
        $this->assertEquals('idle', $model->status());
        $this->assertNull($model->lockedUntil());

        $projection->updateProjection('balance', [
            'position' => '{"balance":10}',
            'state' => '{"balance_count":0}',
            'status' => 'running',
            'locked_until' => '2021-05-27T06:32:46.523885',
        ]);

        $this->assertEquals([
            'no' => 1,
            'name' => 'balance',
            'position' => '{"balance":10}',
            'state' => '{"balance_count":0}',
            'status' => 'running',
            'locked_until' => '2021-05-27T06:32:46.523885',
        ], $projection->newQuery()->find(1)->toArray());
    }

    #[Test]
    public function it_raise_exception_if_projection_name_already_exists(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23000]: Integrity constraint violation');

        $projection = new Projection();

        $projection->createProjection('account', ProjectionStatus::IDLE->value);
        $projection->createProjection('account', ProjectionStatus::RUNNING->value);
    }

    #[Test]
    public function it_assert_projection_exists(): void
    {
        $projection = new Projection();

        $this->assertFalse($projection->projectionExists('balance'));

        $projection->createProjection('balance', ProjectionStatus::IDLE->value);

        $this->assertTrue($projection->projectionExists('balance'));
    }

    #[Test]
    public function it_find_projection_by_name(): void
    {
        $projection = new Projection();

        $projection->createProjection('balance', ProjectionStatus::IDLE->value);

        $this->assertNull($projection->retrieve('account'));
        $this->assertInstanceOf(ProjectionModel::class, $projection->retrieve('balance'));
    }

    #[Test]
    public function it_find_projection_by_sorting_names_ascendant(): void
    {
        $projection = new Projection();

        $projection->createProjection('balance', ProjectionStatus::IDLE->value);
        $projection->createProjection('account', ProjectionStatus::RUNNING->value);

        $this->assertEquals([], $projection->filterByNames('unknown_stream_one', 'unknown_stream_two'));
        $this->assertEquals(['balance'], $projection->filterByNames('unknown_stream', 'balance'));
        $this->assertEquals(['account', 'balance'], $projection->filterByNames('balance', 'account'));
        $this->assertEquals(['account', 'balance'], $projection->filterByNames('balance', 'unknown_stream', 'account'));
    }

    #[Test]
    public function it_delete_projection_by_name(): void
    {
        $projection = new Projection();

        $projection->createProjection('account', ProjectionStatus::IDLE->value);
        $projection->createProjection('balance', ProjectionStatus::RUNNING->value);

        $this->assertEquals(['account', 'balance'], $projection->filterByNames('balance', 'account'));

        $this->assertTrue($projection->deleteProjection('account'));
        $this->assertNull($projection->retrieve('account'));

        $this->assertTrue($projection->deleteProjection('balance'));
        $this->assertNull($projection->retrieve('balance'));
    }

    #[Test]
    public function it_always_acquire_lock_when_locked_until_is_null(): void
    {
        $projection = new Projection();
        $projection->createProjection('account', ProjectionStatus::IDLE->value);

        $this->assertNull($this->findFirstProjection()->lockedUntil());

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $lockedUntil = $now->add(new DateInterval('PT10S'))->format(PointInTime::DATE_TIME_FORMAT);

        $result = $projection->acquireLock('account', 'running', $lockedUntil, $now->format(PointInTime::DATE_TIME_FORMAT));
        $this->assertTrue($result);

        /** @var Projection $updatedProjection */
        $updatedProjection = $projection->newQuery()->find(1);

        $this->assertEquals('running', $updatedProjection->status());
        $this->assertEquals($lockedUntil, $updatedProjection->lockedUntil());
    }

    #[Test]
    public function it_always_acquire_lock_when_locked_until_from_database_is_less_than_now(): void
    {
        $projection = new Projection();
        $projection->createProjection('account', ProjectionStatus::IDLE->value);

        $this->assertNull($projection->retrieve('account')->lockedUntil());

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $lockedUntil = $now->sub(new DateInterval('PT10S'))->format(PointInTime::DATE_TIME_FORMAT);

        $result = $projection->acquireLock('account', 'running', $lockedUntil, $now->format(PointInTime::DATE_TIME_FORMAT));
        $this->assertTrue($result);

        $updatedProjection = $this->findFirstProjection();

        $this->assertEquals('running', $updatedProjection->status());
        $this->assertEquals($lockedUntil, $updatedProjection->lockedUntil());

        $updatedLockedUntil = $now->add(new DateInterval('PT10S'))->format(PointInTime::DATE_TIME_FORMAT);
        $result = $projection->acquireLock('account', 'running', $updatedLockedUntil, $now->format(PointInTime::DATE_TIME_FORMAT));

        $this->assertTrue($result);
        $this->assertEquals($updatedLockedUntil, $this->findFirstProjection()->lockedUntil());
    }

    protected function getPackageProviders($app): array
    {
        return [ProjectorServiceProvider::class];
    }

    private function findFirstProjection(): ?Projection
    {
        $instance = new Projection();

        return $instance->newQuery()->find(1);
    }
}
