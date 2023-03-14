<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;

final class PgsqlTransactionalEventStore extends PgsqlEventStore implements TransactionalChronicler
{
    use ProvideEventStoreTransaction;
}
