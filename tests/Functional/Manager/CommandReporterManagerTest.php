<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Manager;

use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Storm\Reporter\ReportCommand;
use Chronhub\Storm\Tracker\TrackMessage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CqrsManager::class)]
final class CommandReporterManagerTest extends AbstractReporterManagerSetup
{
    private DomainType $domainType = DomainType::COMMAND;

    public function testInstance(): void
    {
        $reporterName = 'default';

        $group = $this->registrar->make($this->domainType, 'default');
        $group->withStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, $reporterName);

        $this->assertEquals(ReportCommand::class, $reporter::class);
        $this->assertInstanceOf(TrackMessage::class, $reporter->tracker());

        $sameReporter = $this->manager->command($reporterName);

        $this->assertNotSame($reporter, $sameReporter);
    }

    public function testCreateGroups(): void
    {
        $group = $this->registrar->make($this->domainType, 'default');
        $anotherGroup = $this->registrar->make($this->domainType, 'another');

        $group->withStrategy('sync');
        $anotherGroup->withStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, 'default');
        $anotherReporter = $this->manager->create($this->domainType->value, 'another');

        $this->assertInstanceOf(ReportCommand::class, $reporter);
        $this->assertInstanceOf(ReportCommand::class, $anotherReporter);

        $this->assertEquals($reporter, $anotherReporter);
        $this->assertCount(1, $this->registrar->all());

        $this->assertSame($group, $this->registrar->get($this->domainType, 'default'));
        $this->assertSame($anotherGroup, $this->registrar->get($this->domainType, 'another'));
    }
}
