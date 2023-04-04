<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Cqrs;

use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Reporter\ReportEvent;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Larastorm\Support\Facade\Report;
use PHPUnit\Framework\Attributes\CoversClass;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;

#[CoversClass(Report::class)]
final class ReportFacadeTest extends OrchestraTestCase
{
    private Registrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrar = $this->app[Registrar::class];
    }

    public function testFacadeRoot(): void
    {
        $root = Report::getFacadeRoot();

        $this->assertInstanceOf(ReporterManager::class, $root);
        $this->assertEquals(CqrsManager::class, $root::class);
    }

    public function testInstance(): void
    {
        $this->registrar
            ->make(DomainType::COMMAND, 'default')
            ->withStrategy('sync');

        $reporter = Report::create('command', 'default');

        $this->assertInstanceOf(ReportCommand::class, $reporter);

        $this->assertEquals($reporter, Report::create('command', 'default'));
        $this->assertNotSame($reporter, Report::create('command', 'default'));
    }

    public function testCreateReportCommandInstance(): void
    {
        $this->registrar
            ->make(DomainType::COMMAND, 'default')
            ->withStrategy('sync');

        $reporter = Report::command('default');

        $this->assertInstanceOf(ReportCommand::class, $reporter);
    }

    public function testCreateReportEventInstance(): void
    {
        $this->registrar
            ->make(DomainType::EVENT, 'default')
            ->withStrategy('sync');

        $reporter = Report::event('default');

        $this->assertInstanceOf(ReportEvent::class, $reporter);
    }

    public function testCreateReportQueryInstance(): void
    {
        $this->registrar
            ->make(DomainType::QUERY, 'default')
            ->withStrategy('sync');

        $reporter = Report::query('default');

        $this->assertInstanceOf(ReportQuery::class, $reporter);
    }

    public function testGetUnderlyingTracker(): void
    {
        $this->registrar
            ->make(DomainType::COMMAND, 'default')
            ->withStrategy('sync');

        $reporter = Report::create('command', 'default');

        $this->assertEquals(TrackMessage::class, $reporter->tracker()::class);
    }

    public function testServiceId(): void
    {
        $this->assertEquals('cqrs.manager', Report::SERVICE_ID);
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
