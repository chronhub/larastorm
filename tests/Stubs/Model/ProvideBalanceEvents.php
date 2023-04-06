<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Model;

use Generator;
use Symfony\Component\Uid\Uuid;
use Chronhub\Storm\Clock\PointInTime;
use Chronhub\Storm\Contracts\Message\Header;
use Chronhub\Storm\Contracts\Message\EventHeader;
use function abs;

trait ProvideBalanceEvents
{
    protected function makeTransaction(BalanceId $balanceId, int $amount, int $limit, int $currentVersion = 0): Generator
    {
        $eventType = $amount < 0 ? BalanceWasDebited::class : BalanceWasCredited::class;

        $headers = [
            Header::EVENT_ID => Uuid::v4()->jsonSerialize(),
            Header::EVENT_TYPE => $eventType,
            Header::EVENT_TIME => (new PointInTime())->now()->format(PointInTime::DATE_TIME_FORMAT),
            EventHeader::AGGREGATE_ID => $balanceId->toString(),
            EventHeader::AGGREGATE_ID_TYPE => BalanceId::class,
            EventHeader::AGGREGATE_TYPE => Balance::class,
        ];

        $version = $currentVersion + 1;

        while (0 !== $limit) {
            $event = $eventType::fromContent(['aggregate_id' => $balanceId->toString(), 'amount' => abs($amount)]);

            yield $event->withHeaders(
                $headers + [
                    EventHeader::INTERNAL_POSITION => $version,
                    EventHeader::AGGREGATE_VERSION => $version,
                ]
            );

            $version++;

            $limit--;
        }
    }
}
