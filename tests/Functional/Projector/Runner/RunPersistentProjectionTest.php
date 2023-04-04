<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Functional\Projector\Runner;

use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Storm\Stream\StreamName;
use Chronhub\Storm\Reporter\DomainEvent;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Larastorm\Support\Facade\Project;
use Chronhub\Larastorm\Tests\OrchestraTestCase;
use Chronhub\Larastorm\Support\Facade\Chronicle;
use Chronhub\Larastorm\Tests\Stubs\Model\Balance;
use Chronhub\Storm\Contracts\Message\EventHeader;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceId;
use Chronhub\Storm\Contracts\Chronicler\Chronicler;
use Chronhub\Larastorm\Providers\ClockServiceProvider;
use Chronhub\Larastorm\Providers\MessagerServiceProvider;
use Chronhub\Larastorm\Providers\ProjectorServiceProvider;
use Chronhub\Larastorm\Providers\ChroniclerServiceProvider;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasDebited;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasCredited;
use Chronhub\Larastorm\Tests\Stubs\Model\BalanceWasRegistered;
use Chronhub\Storm\Contracts\Projector\EmitterCasterInterface;
use Chronhub\Storm\Contracts\Projector\ProjectorManagerInterface;
use function random_int;

final class RunPersistentProjectionTest extends OrchestraTestCase
{
    private Chronicler $eventStore;

    private ProjectorManagerInterface $projector;

    private PointInTime $eventTime;

    public function setUp(): void
    {
        parent::setUp();

        $this->eventStore = Chronicle::setDefaultDriver('in_memory')->create('standalone');
        $this->projector = Project::setDefaultDriver('in_memory')->create('testing');
        $this->eventTime = new PointInTime();
    }

    public function testRunProjection(): void
    {
        $streamName = new StreamName('balance');

        $this->assertFalse($this->projector->exists('balance_projection'));
        $this->assertFalse($this->eventStore->hasStream($streamName));

        $balanceId = BalanceId::fromString('b93e9eba-ed60-47f7-8f23-044e4905dd34');

        $firstEvent = BalanceWasRegistered::withBalance($balanceId)
            ->withHeader(EventHeader::AGGREGATE_VERSION, 1)
            ->withHeader(EventHeader::AGGREGATE_ID, $balanceId->toString())
            ->withHeader(Header::EVENT_TIME, $this->generateTime());

        $this->eventStore->firstCommit(new Stream($streamName, [$firstEvent]));
        $this->assertTrue($this->eventStore->hasStream($streamName));

        [$streamEvents, $expectedAmount] = $this->generateBalanceEvents($balanceId, 2, 5);

        $this->eventStore->amend(new Stream($streamName, $streamEvents));

        $projection = $this->projector->emitter('balance_projection');

        $projection->fromStreams('balance')
            ->initialize(fn (): array => ['balance' => [], 'total' => 0])
            ->withQueryFilter($this->projector->queryScope()->fromIncludedPosition())
            ->whenAny(function (DomainEvent $event, array $state): array {
                /** @var EmitterCasterInterface $this */
                if ($event instanceof BalanceWasRegistered) {
                    $state[$this->streamName()] = [$event->balanceId()->toString() => 0];

                    return $state;
                }

                if ($event instanceof BalanceWasCredited) {
                    $state[$this->streamName()][$event->balanceId()->toString()] += $event->amount();
                    $state['total'] += $event->amount();

                    return $state;
                }

                if ($event instanceof BalanceWasDebited) {
                    $state[$this->streamName()][$event->balanceId()->toString()] -= $event->amount();
                    $state['total'] -= $event->amount();

                    return $state;
                }

                return $state;
            })
            ->run(false);

        $this->assertTrue($this->projector->exists('balance_projection'));

        $this->assertEquals($expectedAmount, $projection->getState()['balance']['b93e9eba-ed60-47f7-8f23-044e4905dd34']);

        $es = $this->eventStore->retrieveAll($streamName, $balanceId);
        $root = Balance::reconstitute($balanceId, $es);

        $this->assertSame($projection->getState()['total'], $root->getAmount());
    }

    private function generateBalanceEvents(BalanceId $balanceId, int $from, int $to, ?bool $isCredit = null): array
    {
        $version = $from;
        $events = [];
        $totalAmount = 0;
        $threshold = 200;

        for ($i = $from; $i <= $to; $i++) {
            $headers = [
                EventHeader::AGGREGATE_VERSION => $version,
                EventHeader::AGGREGATE_ID => $balanceId->toString(),
                Header::EVENT_TIME => $this->generateTime(),
            ];

            $amount = random_int(50, 400);

            $events[] = $isCredit ?? ($amount > $threshold)
                ? BalanceWasCredited::withAmount($balanceId, $amount)->withHeaders($headers)
                : BalanceWasDebited::withAmount($balanceId, $amount)->withHeaders($headers);

            $totalAmount += $isCredit ?? ($amount > $threshold) ? $amount : -$amount;

            $version++;
        }

        return [$events, $totalAmount];
    }

    private function generateTime(): string
    {
        return $this->eventTime->now()->format($this->eventTime::DATE_TIME_FORMAT);
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
