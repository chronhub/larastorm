<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector;

use DateInterval;
use DateTimeZone;
use JsonSerializable;
use DateTimeImmutable;
use Chronhub\Storm\Clock\PointInTime;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Storm\Projector\ProjectionStatus;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Chronhub\Larastorm\Projection\ConnectionProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionModel;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Storm\Contracts\Projector\ProjectionProvider as Provider;

#[CoversClass(ConnectionProvider::class)]
final class ProjectionProviderTest extends OrchestraTestCase
{
    use RefreshDatabase;

    private ConnectionProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new ConnectionProvider($this->app['db']->connection());
    }

    #[Test]
    public function it_assert_projection_provider(): void
    {
        $this->assertInstanceOf(Provider::class, $this->provider);
        $this->assertNull($this->findProjectionByName('foo'));
        $this->assertEquals('projections', $this->provider::TABLE_NAME);
    }

    #[Test]
    public function it_create_projection(): void
    {
        $created = $this->provider->createProjection('balance', ProjectionStatus::IDLE->value);

        $this->assertTrue($created);

        $model = $this->provider->retrieve('balance');

        $this->assertInstanceOf(ProjectionModel::class, $model);
        $this->assertInstanceOf(JsonSerializable::class, $model);

        $this->assertEquals([
            'name' => 'balance',
            'position' => '{}',
            'state' => '{}',
            'status' => 'idle',
            'locked_until' => null,
        ], $model->jsonSerialize());

        $this->assertEquals('balance', $model->name());
        $this->assertEquals('{}', $model->position());
        $this->assertEquals('{}', $model->state());
        $this->assertEquals('idle', $model->status());
        $this->assertNull($model->lockedUntil());
    }

    #[Test]
    public function it_update_projection_by_projection_name(): void
    {
        $created = $this->provider->createProjection('balance', ProjectionStatus::IDLE->value);
        $this->assertTrue($created);

        $model = $this->provider->retrieve('balance');

        $this->assertInstanceOf(ProjectionModel::class, $model);
        $this->assertInstanceOf(JsonSerializable::class, $model);

        $this->assertEquals([
            'name' => 'balance',
            'position' => '{}',
            'state' => '{}',
            'status' => 'idle',
            'locked_until' => null,
        ], $model->jsonSerialize());

        $this->assertEquals('balance', $model->name());
        $this->assertEquals('{}', $model->position());
        $this->assertEquals('{}', $model->state());
        $this->assertEquals('idle', $model->status());
        $this->assertNull($model->lockedUntil());

        $this->provider->updateProjection('balance', [
            'position' => '{"balance":10}',
            'state' => '{"balance_count":0}',
            'status' => 'running',
            'locked_until' => '2021-05-27T06:32:46.523885',
        ]);

        $model = $this->provider->retrieve('balance');

        $this->assertInstanceOf(ProjectionModel::class, $model);
        $this->assertInstanceOf(JsonSerializable::class, $model);

        $this->assertEquals([
            'name' => 'balance',
            'position' => '{"balance":10}',
            'state' => '{"balance_count":0}',
            'status' => 'running',
            'locked_until' => '2021-05-27T06:32:46.523885',
        ], $model->jsonSerialize());
    }

    #[Test]
    public function it_raise_exception_if_projection_name_already_exists(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('SQLSTATE[23000]: Integrity constraint violation');

        $this->provider->createProjection('account', ProjectionStatus::IDLE->value);
        $this->provider->createProjection('account', ProjectionStatus::RUNNING->value);
    }

    #[Test]
    public function it_assert_projection_exists(): void
    {
        $this->assertFalse($this->provider->projectionExists('balance'));

        $this->provider->createProjection('balance', ProjectionStatus::IDLE->value);

        $this->assertTrue($this->provider->projectionExists('balance'));
    }

    #[Test]
    public function it_find_projection_by_name(): void
    {
        $this->provider->createProjection('balance', ProjectionStatus::IDLE->value);

        $this->assertNull($this->provider->retrieve('account'));
        $this->assertInstanceOf(ProjectionModel::class, $this->provider->retrieve('balance'));
    }

    #[Test]
    public function it_find_projection_by_sorting_names_ascendant(): void
    {
        $this->provider->createProjection('balance', ProjectionStatus::IDLE->value);
        $this->provider->createProjection('account', ProjectionStatus::RUNNING->value);

        $this->assertEquals([], $this->provider->filterByNames('unknown_stream_one', 'unknown_stream_two'));
        $this->assertEquals(['balance'], $this->provider->filterByNames('unknown_stream', 'balance'));
        $this->assertEquals(['account', 'balance'], $this->provider->filterByNames('balance', 'account'));
        $this->assertEquals(['account', 'balance'], $this->provider->filterByNames('balance', 'unknown_stream', 'account'));
    }

    #[Test]
    public function it_delete_projection_by_name(): void
    {
        $this->provider->createProjection('account', ProjectionStatus::IDLE->value);
        $this->provider->createProjection('balance', ProjectionStatus::RUNNING->value);

        $this->assertEquals(['account', 'balance'], $this->provider->filterByNames('balance', 'account'));

        $this->assertTrue($this->provider->deleteProjection('account'));
        $this->assertNull($this->provider->retrieve('account'));

        $this->assertTrue($this->provider->deleteProjection('balance'));
        $this->assertNull($this->provider->retrieve('balance'));
    }

    #[Test]
    public function it_always_acquire_lock_when_locked_until_is_null(): void
    {
        $this->provider->createProjection('account', ProjectionStatus::IDLE->value);

        $this->assertNull($this->findProjectionByName('account')->lockedUntil());

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $lockedUntil = $now->add(new DateInterval('PT10S'))->format(PointInTime::DATE_TIME_FORMAT);

        $result = $this->provider->acquireLock('account', 'running', $lockedUntil, $now->format(PointInTime::DATE_TIME_FORMAT));
        $this->assertTrue($result);

        $updatedProjection = $this->provider->retrieve('account');

        $this->assertEquals('running', $updatedProjection->status());
        $this->assertEquals($lockedUntil, $updatedProjection->lockedUntil());
    }

    #[Test]
    public function it_always_acquire_lock_when_locked_until_from_database_is_less_than_now(): void
    {
        $this->provider->createProjection('account', ProjectionStatus::IDLE->value);

        $this->assertNull($this->provider->retrieve('account')->lockedUntil());

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $lockedUntil = $now->sub(new DateInterval('PT10S'))->format(PointInTime::DATE_TIME_FORMAT);

        $result = $this->provider->acquireLock('account', 'running', $lockedUntil, $now->format(PointInTime::DATE_TIME_FORMAT));
        $this->assertTrue($result);

        $updatedProjection = $this->findProjectionByName('account');

        $this->assertEquals('running', $updatedProjection->status());
        $this->assertEquals($lockedUntil, $updatedProjection->lockedUntil());

        $updatedLockedUntil = $now->add(new DateInterval('PT10S'))->format(PointInTime::DATE_TIME_FORMAT);
        $result = $this->provider->acquireLock('account', 'running', $updatedLockedUntil, $now->format(PointInTime::DATE_TIME_FORMAT));

        $this->assertTrue($result);
        $this->assertEquals($updatedLockedUntil, $this->findProjectionByName('account')->lockedUntil());
    }

    protected function getPackageProviders($app): array
    {
        return [ProjectorServiceProvider::class];
    }

    private function findProjectionByName(string $name): ?ProjectionModel
    {
        return $this->provider->retrieve($name);
    }
}
