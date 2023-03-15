<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Manager;

use PHPUnit\Framework\Attributes\Test;
use Chronhub\Storm\Reporter\DomainType;
use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Storm\Reporter\ReportQuery;
use Chronhub\Storm\Tracker\TrackMessage;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CqrsManager::class)]
final class QueryReporterManagerTest extends AbstractReporterManagerSetup
{
    private DomainType $domainType = DomainType::QUERY;

    #[Test]
    public function it_create_query_reporter(): void
    {
        $reporterName = 'default';

        $group = $this->registrar->make($this->domainType, 'default');
        $group->withStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, $reporterName);

        $this->assertInstanceOf(ReportQuery::class, $reporter);
        $this->assertInstanceOf(TrackMessage::class, $reporter->tracker());

        $sameReporter = $this->manager->query($reporterName);

        $this->assertNotSame($reporter, $sameReporter);
    }

    #[Test]
    public function it_create_same_query_reporter_under_one_group_type(): void
    {
        $group = $this->registrar->make($this->domainType, 'default');
        $anotherGroup = $this->registrar->make($this->domainType, 'another');

        $group->withStrategy('sync');
        $anotherGroup->withStrategy('sync');

        $reporter = $this->manager->create($this->domainType->value, 'default');
        $anotherReporter = $this->manager->create($this->domainType->value, 'another');

        $this->assertInstanceOf(ReportQuery::class, $reporter);
        $this->assertInstanceOf(ReportQuery::class, $anotherReporter);

        $this->assertEquals($reporter, $anotherReporter);
        $this->assertNotSame($reporter, $anotherReporter);
        $this->assertCount(1, $this->registrar->all());

        $this->assertSame($group, $this->registrar->get($this->domainType, 'default'));
        $this->assertSame($anotherGroup, $this->registrar->get($this->domainType, 'another'));
    }

    #[Test]
    public function it_create_different_query_reporter_under_one_group_type(): void
    {
        $group = $this->registrar->make($this->domainType, 'default');
        $anotherGroup = $this->registrar->make($this->domainType, 'another');

        $group
            ->withStrategy('sync')
            ->withReporterId('reporter.default');

        $anotherGroup
            ->withStrategy('sync')
            ->withReporterId('reporter.another');

        $reporter = $this->manager->create($this->domainType->value, 'default');
        $anotherReporter = $this->manager->query('another');

        $this->assertInstanceOf(ReportQuery::class, $reporter);
        $this->assertInstanceOf(ReportQuery::class, $anotherReporter);

        $this->assertEquals($reporter, $anotherReporter);
        $this->assertNotSame($reporter, $anotherReporter);
        $this->assertCount(1, $this->registrar->all());

        $this->assertSame($group, $this->registrar->get($this->domainType, 'default'));
        $this->assertSame($anotherGroup, $this->registrar->get($this->domainType, 'another'));
    }
}
