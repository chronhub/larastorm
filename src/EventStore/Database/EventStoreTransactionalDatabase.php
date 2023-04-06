<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Chronhub\Storm\Chronicler\Exceptions\TransactionAlreadyStarted;
use Chronhub\Storm\Chronicler\Exceptions\TransactionNotStarted;
use Chronhub\Storm\Contracts\Chronicler\TransactionalChronicler;
use Throwable;

final class EventStoreTransactionalDatabase extends EventStoreDatabase implements TransactionalChronicler
{
    public function beginTransaction(): void
    {
        try {
            $this->connection->beginTransaction();
        } catch (Throwable) {
            throw new TransactionAlreadyStarted('Transaction already started');
        }
    }

    public function commitTransaction(): void
    {
        try {
            $this->connection->commit();
        } catch (Throwable) {
            throw new TransactionNotStarted('Transaction not started');
        }
    }

    public function rollbackTransaction(): void
    {
        try {
            $this->connection->rollBack();
        } catch (Throwable) {
            throw new TransactionNotStarted('Transaction not started');
        }
    }

    public function inTransaction(): bool
    {
        return $this->connection->transactionLevel() > 0;
    }

    public function transactional(callable $callback): bool|array|string|int|float|object
    {
        $this->beginTransaction();

        try {
            $result = $callback($this);

            $this->commitTransaction();
        } catch (Throwable $exception) {
            $this->rollbackTransaction();

            throw $exception;
        }

        return $result ?? true;
    }
}
