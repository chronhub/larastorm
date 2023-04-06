<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Manager;

use Chronhub\Larastorm\Cqrs\CqrsManager;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\CqrsServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Storm\Contracts\Reporter\ReporterManager;
use Chronhub\Storm\Contracts\Routing\Registrar;
use Chronhub\Storm\Reporter\DomainType;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CqrsManager::class)]
abstract class AbstractReporterManagerSetup extends OrchestraTestCase
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

    public static function provideDomainType(): Generator
    {
        yield [DomainType::COMMAND];
        yield [DomainType::EVENT];
        yield [DomainType::QUERY];
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
