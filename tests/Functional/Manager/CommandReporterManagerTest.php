<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Manager;

use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Tracker\TrackMessage;
use Chronhub\Storm\Reporter\ReportCommand;

final class CommandReporterManagerTest extends CqrsManagerTest
{
    private DomainType $domainType = DomainType::COMMAND;

    /**
     * @test
     */
    public function it_create_command_reporter(): void
    {
        $reporterName = 'default';

        $group = $this->registrar->make($this->domainType, 'default');
        $group->withProducerStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, $reporterName);

        $this->assertEquals(ReportCommand::class, $reporter::class);
        $this->assertInstanceOf(TrackMessage::class, $reporter->tracker());

        $sameReporter = $this->manager->command($reporterName);

        $this->assertNotSame($reporter, $sameReporter);
    }

    /**
     * @test
     */
    public function it_create_same_command_reporter_under_one_group_type(): void
    {
        $group = $this->registrar->make($this->domainType, 'default');
        $anotherGroup = $this->registrar->make($this->domainType, 'another');

        $group->withProducerStrategy('sync');
        $anotherGroup->withProducerStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, 'default');
        $anotherReporter = $this->manager->create($this->domainType->value, 'another');

        $this->assertInstanceOf(ReportCommand::class, $reporter);
        $this->assertInstanceOf(ReportCommand::class, $anotherReporter);

        $this->assertEquals($reporter, $anotherReporter);
        $this->assertCount(1, $this->registrar->all());

        $this->assertSame($group, $this->registrar->get($this->domainType, 'default'));
        $this->assertSame($anotherGroup, $this->registrar->get($this->domainType, 'another'));
    }

    /**
     * @test
     */
    public function it_create_different_command_reporter_under_one_group_type(): void
    {
        $group = $this->registrar->make($this->domainType, 'default');
        $anotherGroup = $this->registrar->make($this->domainType, 'another');

        $group
            ->withReporterServiceId('reporter.default')
            ->withProducerStrategy('sync');

        $anotherGroup
            ->withReporterServiceId('reporter.another')
            ->withProducerStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, 'default');
        $anotherReporter = $this->manager->command('another');

        $this->assertInstanceOf(ReportCommand::class, $reporter);
        $this->assertInstanceOf(ReportCommand::class, $anotherReporter);

        $this->assertEquals($reporter, $anotherReporter);
        $this->assertNotSame($reporter, $anotherReporter);
        $this->assertCount(1, $this->registrar->all());

        $this->assertSame($group, $this->registrar->get($this->domainType, 'default'));
        $this->assertSame($anotherGroup, $this->registrar->get($this->domainType, 'another'));
    }
}
