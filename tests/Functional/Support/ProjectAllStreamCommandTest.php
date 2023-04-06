<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Generator;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceId;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasDebited;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasCredited;
use Chronhub\Larastorm\Tests\Stubs\Model\ProvideBalanceEvents;
use Chronhub\Storm\Contracts\Projector\EmitterCasterInterface;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;

final class ProjectAllStreamCommandTest extends OrchestraTestCase
{
    use ProvideBalanceEvents;

    private BalanceId $balanceId;

    private ProjectorManagerInterface $projector;

    private Chronicler $eventStore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->balanceId = BalanceId::create();
        $this->projector = Project::setDefaultDriver('in_memory')->create('testing');
        $this->eventStore = Chronicle::setDefaultDriver('in_memory')->create('standalone');
    }

    public function testProjectAllStreams(): void
    {
        $allStreams = new StreamName('$all');
        $credit = new StreamName('credit');
        $debit = new StreamName('debit');

        $this->assertFalse($this->eventStore->hasStream($allStreams));
        $this->assertFalse($this->eventStore->hasStream($credit));
        $this->assertFalse($this->eventStore->hasStream($debit));

        $this->eventStore->firstCommit(new Stream($credit));
        $this->eventStore->firstCommit(new Stream($debit));

        $this->assertTrue($this->eventStore->hasStream($credit));
        $this->assertTrue($this->eventStore->hasStream($debit));

        $this->assertFalse($this->projector->exists('operation'));
        $this->assertFalse($this->projector->exists('$all'));

        $this->feedChronicler($credit, $this->makeTransaction($this->balanceId, 100, 1));
        $this->feedChronicler($debit, $this->makeTransaction($this->balanceId, -100, 1, 1));

        $this->assertFalse($this->projector->exists('operation'));
        $projection = $this->projector->emitter('operation');

        $projection
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromAll()
            ->whenAny(function (BalanceWasCredited|BalanceWasDebited $event): void {
                /** @var EmitterCasterInterface $this */
                if ($event instanceof BalanceWasCredited) {
                    UnitTestCase::assertSame('credit', $this->streamName());
                } else {
                    UnitTestCase::assertSame('debit', $this->streamName());
                }

                UnitTestcase::assertNotSame('$all', $this->streamName());
            })->run(false);

        $this->artisan('projector:edge-all', ['projector' => 'testing', '--in-background' => 0]);

        $this->assertTrue($this->projector->exists('operation'));
        $this->assertTrue($this->projector->exists('$all'));
        $this->assertTrue($this->eventStore->hasStream($allStreams));

        $this->assertEquals(
            ['operation', '$all'],
            $this->projector->filterNamesByAscendantOrder($credit->name, $debit->name, $allStreams->name, 'operation'),
        );
    }

    protected function feedChronicler(StreamName $streamName, Generator $events): void
    {
        $this->eventStore->amend(new Stream($streamName, $events));
    }

    protected function getPackageProviders($app): array
    {
        return [
            ClockServiceProvider::class,
            MessagerServiceProvider::class,
            ChroniclerServiceProvider::class,
            ProjectorServiceProvider::class,
        ];
    }
}
