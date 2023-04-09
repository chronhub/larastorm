<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;

final class MysqlTransactionalEventStore extends MysqlEventStore implements TransactionalChronicler
{
    use InteractWithEventStoreTransaction;
}
