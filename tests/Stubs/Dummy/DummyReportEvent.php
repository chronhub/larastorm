<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\Tests\Stubs\Dummy;

use Chronhub\Storm\Contracts\Reporter\EventReporter;
use Chronhub\Storm\Reporter\Concern\HasConstructableReporter;
use Chronhub\Storm\Reporter\Concern\InteractWithReporter;
use Chronhub\Storm\Reporter\DomainType;

final class DummyReportEvent implements EventReporter
{
    use HasConstructableReporter;
    use InteractWithReporter;

    public function relay(object|array $message): void
    {
        $this->relayEvent($message);
    }

    public function getType(): DomainType
    {
        return DomainType::EVENT;
    }
}
