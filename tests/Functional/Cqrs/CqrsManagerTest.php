<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Cqrs;

use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Larastorm\Cqrs\MessageProducerFactory;
use Chronhub\Larastorm\Cqrs\MessageSubscribersFactory;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Stubs\Dummy\DummyReportCommand;
use Chronhub\Larastorm\Tests\Stubs\Dummy\DummyReportEvent;
use Chronhub\Larastorm\Tests\Stubs\Dummy\DummyReportQuery;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Routing\Exceptions\RoutingViolation;
use Chronhub\Storm\Tracker\TrackMessage;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testCreateDefaultCommandReporter(): void
    {
        $reporter = $this->manager->command();

        $this->assertEquals(ReportCommand::class, $reporter::class);
    }

    public function testCreateAnotherCommandReporter(): void
    {
        $reporter = $this->manager->command('another');

        $this->assertEquals(ReportCommand::class, $reporter::class);
    }

    public function testCreateExtendedCommandReporter(): void
    {
        $reporter = $this->manager->command('dummy');

        $this->assertEquals(DummyReportCommand::class, $reporter::class);
    }

    public function testCreateDefaultEventReporter(): void
    {
        $reporter = $this->manager->event();

        $this->assertEquals(ReportEvent::class, $reporter::class);
    }

    public function testCreateAnotherEventReporter(): void
    {
        $reporter = $this->manager->event('another');

        $this->assertEquals(ReportEvent::class, $reporter::class);
    }

    public function testCreateExtendedEventReporter(): void
    {
        $reporter = $this->manager->event('dummy');

        $this->assertEquals(DummyReportEvent::class, $reporter::class);
    }

    public function testCreateDefaultQueryReporter(): void
    {
        $reporter = $this->manager->query();

        $this->assertEquals(ReportQuery::class, $reporter::class);
    }

    public function testCreateAnotherQueryReporter(): void
    {
        $reporter = $this->manager->query('another');

        $this->assertEquals(ReportQuery::class, $reporter::class);
    }

    public function testCreateExtendedQueryReporter(): void
    {
        $reporter = $this->manager->query('dummy');

        $this->assertEquals(DummyReportQuery::class, $reporter::class);
    }

    #[DataProvider('provideDomainType')]
    public function testInstance(string $domainType): void
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
    public function testResolveStringTrackerConfigFromIoc(string $domainType): void
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
    public function testExceptionRaisedWhenServiceProducerIsNotProvided(string $domainType): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage('Producer strategy can not be null');

        $this->app[Registrar::class]->make(DomainType::from($domainType), 'strategy_not_defined');

        $this->manager->create($domainType, 'strategy_not_defined');
    }

    public function testExceptionRaisedWhenGroupNameIsNotRegistered(): void
    {
        $this->expectException(RoutingViolation::class);
        $this->expectExceptionMessage('Group with type command and name foo not defined');

        $this->manager->command('foo');
    }

    public function testExceptionRaisedWhenGroupTypeIsInvalid(): void
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
            ->withReporterConcrete(DummyReportCommand::class)
            ->withStrategy('sync');

        $registrar->make(DomainType::EVENT, 'dummy')
            ->withReporterConcrete(DummyReportEvent::class)
            ->withStrategy('sync');

        $registrar->make(DomainType::QUERY, 'dummy')
            ->withReporterConcrete(DummyReportQuery::class)
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
