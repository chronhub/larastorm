<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Support;

use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Support\Console\Edges\ProjectStreamCategoryCommand;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceId;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasCredited;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasDebited;
use Chronhub\Larastorm\Tests\Stubs\Model\ProvideBalanceEvents;
use Chronhub\Larastorm\Tests\UnitTestCase;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Storm\Contracts\Projector\EmitterCasterInterface;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use Chronhub\Storm\Contracts\Projector\QueryCasterInterface;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProjectStreamCategoryCommand::class)]
final class ProjectStreamCategoryCommandTest extends OrchestraTestCase
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

    public function testProjectStreamCategory(): void
    {
        $categories = new StreamName('$by_category');
        $credit = new StreamName('op-credit');
        $debit = new StreamName('op-debit');
        $notACategory = new StreamName('foo');

        $this->assertFalse($this->eventStore->hasStream($categories));
        $this->assertFalse($this->eventStore->hasStream($credit));
        $this->assertFalse($this->eventStore->hasStream($debit));
        $this->assertFalse($this->eventStore->hasStream($notACategory));

        $this->eventStore->firstCommit(new Stream($credit));
        $this->eventStore->firstCommit(new Stream($debit));
        $this->eventStore->firstCommit(new Stream($notACategory));

        $this->assertTrue($this->eventStore->hasStream($credit));
        $this->assertTrue($this->eventStore->hasStream($debit));
        $this->assertTrue($this->eventStore->hasStream($notACategory));

        $this->assertFalse($this->projector->exists('operation'));
        $this->assertFalse($this->projector->exists('$by_category'));

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
                    UnitTestCase::assertSame('op-credit', $this->streamName());
                } else {
                    UnitTestCase::assertSame('op-debit', $this->streamName());
                }

                UnitTestcase::assertNotSame('$by_category', $this->streamName());
            })->run(false);

        $this->artisan('projector:edge-category', ['projector' => 'testing', '--in-background' => 0]);

        $this->assertTrue($this->projector->exists('operation'));
        $this->assertTrue($this->projector->exists('$by_category'));

        $this->assertTrue($this->eventStore->hasStream(new StreamName('$ct-'.BalanceWasCredited::class)));
        $this->assertTrue($this->eventStore->hasStream(new StreamName('$ct-'.BalanceWasDebited::class)));

        $this->assertEquals(
            ['operation', '$by_category'],
            $this->projector->filterNamesByAscendantOrder(
                $credit->name, $debit->name, $categories->name, 'operation', $notACategory->name
            ),
        );

        $readProjection = $this->projector->query();

        $readProjection
            ->initialize(fn (): array => ['$ct' => []])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->fromCategories('$ct')
            ->whenAny(function (BalanceWasCredited|BalanceWasDebited $event, array $state): array {
                /** @var QueryCasterInterface $this */
                $state['$ct'][] = $this->streamName();

                return $state;
            })->run(false);

        $this->assertEquals([
            '$ct-'.BalanceWasCredited::class,
            '$ct-'.BalanceWasDebited::class,
        ], $readProjection->getState()['$ct']);
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
