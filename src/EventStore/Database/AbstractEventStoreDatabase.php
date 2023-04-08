<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Larastorm\Support\Contracts\ChroniclerDB;
use Chronhub\Larastorm\Support\Contracts\StreamEventLoaderConnection;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Storm\Contracts\Stream\StreamPersistenceWithQueryHint;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use function is_callable;

abstract class AbstractEventStoreDatabase implements ChroniclerDB
{
    /**
     * Determine if we create a new stream, or we update one
     */
    protected bool $isCreation = false;

    public function __construct(
        protected readonly Connection $connection,
        protected readonly StreamPersistence $streamPersistence,
        protected readonly StreamEventLoaderConnection $eventLoader,
        protected readonly EventStreamProvider $eventStreamProvider,
        protected readonly StreamCategory $streamCategory,
        protected readonly WriteLockStrategy $writeLock
    ) {
    }

    public function isDuringCreation(): bool
    {
        return $this->isCreation;
    }

    public function getEventStreamProvider(): EventStreamProvider
    {
        return $this->eventStreamProvider;
    }

    protected function forWrite(StreamName $streamName): Builder
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        $builder = $this->connection->table($tableName)->useWritePdo();

        if ($this->writeLock instanceof MysqlWriteLock) {
            return $builder->lockForUpdate();
        }

        return $builder;
    }

    protected function forRead(StreamName $streamName): Builder
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        if ($this->streamPersistence instanceof StreamPersistenceWithQueryHint) {
            $indexName = $this->streamPersistence->indexName($tableName);

            $raw = "`$tableName` USE INDEX($indexName)";

            return $this->connection->query()->fromRaw($raw);
        }

        return $this->connection->table($tableName);
    }

    protected function serializeStreamEvents(iterable $streamEvents): array
    {
        return tap([], function (array &$events) use ($streamEvents) {
            foreach ($streamEvents as $streamEvent) {
                $events[] = $this->streamPersistence->serialize($streamEvent);
            }

            return $events;
        });
    }

    /**
     * @throws QueryException
     */
    protected function upStreamTable(StreamName $streamName): void
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        try {
            $callback = $this->streamPersistence->up($tableName);

            if (is_callable($callback)) {
                $callback($this->connection);
            }
        } catch (QueryException $exception) {
            $this->connection->getSchemaBuilder()->drop($tableName);

            $this->eventStreamProvider->deleteStream($streamName->name);

            throw $exception;
        }
    }

    /**
     * @throws QueryException
     */
    protected function createEventStream(StreamName $streamName): void
    {
        $created = $this->eventStreamProvider->createStream(
            $streamName->name,
            $this->streamPersistence->tableName($streamName),
            $this->streamCategory->determineFrom($streamName->name)
        );

        if (! $created) {
            throw new ConnectionQueryFailure(
                "Unable to insert data for stream $streamName in event stream table"
            );
        }
    }
}
