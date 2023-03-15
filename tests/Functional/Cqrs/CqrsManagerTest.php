<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Cqrs;

use Generator;
use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Storm\Reporter\ReportCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Larastorm\Cqrs\MessageProducerFactory;
use Chronhub\Larastorm\Tests\Dummy\SomeReportEvent;
use Chronhub\Larastorm\Tests\Dummy\SomeReportQuery;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Tests\Dummy\SomeReportCommand;
use Chronhub\Larastorm\Cqrs\MessageSubscribersFactory;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;

#[CoversClass(CqrsManager::class)]
#[CoversClass(MessageProducerFactory::class)]
#[CoversClass(MessageSubscribersFactory::class)]
class CqrsManagerTest extends OrchestraTestCase
{
    private CqrsManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->app[ReporterManager::class];
    }

    #[Test]
    public function it_create_default_command_reporter(): void
    {
        $reporter = $this->manager->command();

        $this->assertEquals(ReportCommand::class, $reporter::class);
    }

    #[Test]
    public function it_create_another_command_reporter(): void
    {
        $reporter = $this->manager->command('another');

        $this->assertEquals(ReportCommand::class, $reporter::class);
    }

    #[Test]
    public function it_create_command_reporter_with_extended_reporter(): void
    {
        $reporter = $this->manager->command('dummy');

        $this->assertEquals(SomeReportCommand::class, $reporter::class);
    }

    #[Test]
    public function it_create_default_event_reporter(): void
    {
        $reporter = $this->manager->event();

        $this->assertEquals(ReportEvent::class, $reporter::class);
    }

    #[Test]
    public function it_create_another_event_reporter(): void
    {
        $reporter = $this->manager->event('another');

        $this->assertEquals(ReportEvent::class, $reporter::class);
    }

    #[Test]
    public function it_create_event_reporter_with_extended_reporter(): void
    {
        $reporter = $this->manager->event('dummy');

        $this->assertEquals(SomeReportEvent::class, $reporter::class);
    }

    #[Test]
    public function it_create_default_query_reporter(): void
    {
        $reporter = $this->manager->query();

        $this->assertEquals(ReportQuery::class, $reporter::class);
    }

    #[Test]
    public function it_create_another_query_reporter(): void
    {
        $reporter = $this->manager->query('another');

        $this->assertEquals(ReportQuery::class, $reporter::class);
    }

    #[Test]
    public function it_create_query_reporter_with_extended_reporter(): void
    {
        $reporter = $this->manager->query('dummy');

        $this->assertEquals(SomeReportQuery::class, $reporter::class);
    }

    #[DataProvider('provideDomainType')]
    #[Test]
    public function it_return_new_instance(string $domainType): void
    {
        $defaultReporter = $this->manager->create($domainType, 'default');

        $this->assertEquals($defaultReporter, $this->manager->create($domainType, 'default'));
        $this->assertNotSame($defaultReporter, $this->manager->create($domainType, 'default'));

        $anotherReporter = $this->manager->create($domainType, 'another');

        $this->assertEquals($anotherReporter, $this->manager->create($domainType, 'another'));
        $this->assertNotSame($anotherReporter, $this->manager->create($domainType, 'another'));

        $this->assertNotSame($defaultReporter, $anotherReporter);
    }

    #[DataProvider('provideDomainType')]
    #[Test]
    public function it_resolve_string_tracker_from_router(string $domainType): void
    {
        $messageTracker = new TrackMessage();

        $this->app->instance("reporter.$domainType.tracker", $messageTracker);

        $group = $this->app[Registrar::class]->make(DomainType::from($domainType), 'with_tracker');

        $group
            ->withStrategy('sync')
            ->withTrackerId("reporter.$domainType.tracker");

        $reporter = $this->manager->create($domainType, 'with_tracker');

        $this->assertEquals($messageTracker, $reporter->tracker());
    }

    #[DataProvider('provideDomainType')]
    #[Test]
    public function it_raise_exception_when_router_producer_service_is_not_provided(string $domainType): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage('Producer strategy can not be null');

        $this->app[Registrar::class]->make(DomainType::from($domainType), 'strategy_not_defined');

        $this->manager->create($domainType, 'strategy_not_defined');
    }

    #[Test]
    public function it_raise_exception_when_group_name_is_not_registered(): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage('Group with type command and name foo not defined');

        $this->manager->command('foo');
    }

    #[Test]
    public function it_raise_exception_when_group_type_is_invalid(): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage('Group type foo is invalid');

        $this->manager->create('foo', 'bar');
    }

    public static function provideDomainType(): Generator
    {
        yield [DomainType::COMMAND->value];
        yield [DomainType::EVENT->value];
        yield [DomainType::QUERY->value];
    }

    protected function defineEnvironment($app)
    {
        /** @var Registrar $registrar */
        $registrar = $app[Registrar::class];

        $registrar->make(DomainType::COMMAND, 'default')->withStrategy('sync');
        $registrar->make(DomainType::COMMAND, 'another')->withStrategy('sync');
        $registrar->make(DomainType::EVENT, 'default')->withStrategy('sync');
        $registrar->make(DomainType::EVENT, 'another')->withStrategy('sync');
        $registrar->make(DomainType::QUERY, 'default')->withStrategy('sync');
        $registrar->make(DomainType::QUERY, 'another')->withStrategy('sync');

        $registrar->make(DomainType::COMMAND, 'dummy')
            ->withReporterConcrete(SomeReportCommand::class)
            ->withStrategy('sync');

        $registrar->make(DomainType::EVENT, 'dummy')
            ->withReporterConcrete(SomeReportEvent::class)
            ->withStrategy('sync');

        $registrar->make(DomainType::QUERY, 'dummy')
            ->withReporterConcrete(SomeReportQuery::class)
            ->withStrategy('sync');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            MessagerServiceProvider::class,
            CqrsServiceProvider::class,
        ];
    }
}
