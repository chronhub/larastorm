<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Manager;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Tracker\TrackMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;

#[CoversClass(CqrsManager::class)]
class CqrsManagerTest extends OrchestraTestCase
{
    protected Registrar $registrar;

    protected CqrsManager|ReporterManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = $this->app[Registrar::class];
        $this->assertTrue($this->registrar->all()->isEmpty());

        $this->manager = $this->app[ReporterManager::class];
    }

    #[DataProvider('provideDomainType')]
    #[Test]
    public function it_resolve_string_tracker_from_router(DomainType $domainType): void
    {
        $messageTracker = new TrackMessage();

        $this->app->instance('reporter.tracker', $messageTracker);

        $group = $this->registrar->make($domainType, 'default');

        $group
            ->withTrackerId('reporter.tracker')
            ->withProducerStrategy('sync');

        $reporter = $this->manager->create($domainType->value, 'default');

        $this->assertEquals($messageTracker, $reporter->tracker());
    }

    #[DataProvider('provideDomainType')]
    #[Test]
    public function it_raise_exception_when_create_reporter_with_name_and_type_not_found_in_router(DomainType $domainType): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage("Group with type $domainType->value and name default not defined");

        $this->manager->create($domainType->value, 'default');
    }

    #[DataProvider('provideDomainType')]
    #[Test]
    public function it_raise_exception_when_router_producer_service_is_not_provided(DomainType $domainType): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage('Producer strategy can not be null');

        $this->registrar->make($domainType, 'default');

        $this->manager->create($domainType->value, 'default');
    }

    public static function provideDomainType(): Generator
    {
        yield [DomainType::COMMAND];
        yield [DomainType::EVENT];
        yield [DomainType::QUERY];
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            CqrsServiceProvider::class,
        ];
    }
}
