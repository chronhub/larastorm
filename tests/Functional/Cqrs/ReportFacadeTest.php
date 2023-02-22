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
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;

final class ReportFacadeTest extends OrchestraTestCase
{
    private Registrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registrar = $this->app[Registrar::class];
    }

    /**
     * @test
     */
    public function it_assert_root(): void
    {
        $root = Report::getFacadeRoot();

        $this->assertInstanceOf(ReporterManager::class, $root);
        $this->assertEquals(CqrsManager::class, $root::class);
    }

    /**
     * @test
     */
    public function it_create_instance(): void
    {
        $this->registrar
            ->make(DomainType::COMMAND, 'default')
            ->withProducerStrategy('sync');

        $reporter = Report::create('command', 'default');

        $this->assertInstanceOf(ReportCommand::class, $reporter);

        $this->assertEquals($reporter, Report::create('command', 'default'));
        $this->assertNotSame($reporter, Report::create('command', 'default'));
    }

    /**
     * @test
     */
    public function it_create_command_instance(): void
    {
        $this->registrar
            ->make(DomainType::COMMAND, 'default')
            ->withProducerStrategy('sync');

        $reporter = Report::command('default');

        $this->assertInstanceOf(ReportCommand::class, $reporter);
    }

    /**
     * @test
     */
    public function it_create_event_instance(): void
    {
        $this->registrar
            ->make(DomainType::EVENT, 'default')
            ->withProducerStrategy('sync');

        $reporter = Report::event('default');

        $this->assertInstanceOf(ReportEvent::class, $reporter);
    }

    /**
     * @test
     */
    public function it_create_query_instance(): void
    {
        $this->registrar
            ->make(DomainType::QUERY, 'default')
            ->withProducerStrategy('sync');

        $reporter = Report::query('default');

        $this->assertInstanceOf(ReportQuery::class, $reporter);
    }

    /**
     * @test
     */
    public function it_access_tracker(): void
    {
        $this->registrar
            ->make(DomainType::COMMAND, 'default')
            ->withProducerStrategy('sync');

        $reporter = Report::create('command', 'default');

        $this->assertEquals(TrackMessage::class, $reporter->tracker()::class);
    }

    /**
     * @test
     */
    public function it_fix_facade_service_id(): void
    {
        $this->assertEquals('cqrs.manager', Report::SERVICE_ID);
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessagerServiceProvider::class,
            CqrsServiceProvider::class,
        ];
    }
}
