<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore;

use Illuminate\Database\Connection;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Contracts\Stream\StreamCategory;
use Chronhub\Storm\Contracts\Stream\StreamPersistence;
use Chronhub\Larastorm\Exceptions\ConnectionQueryFailure;
use Chronhub\Storm\Contracts\Chronicler\WriteLockStrategy;
use Chronhub\Larastorm\EventStore\WriteLock\MysqlWriteLock;
use Chronhub\Storm\Contracts\Chronicler\EventStreamProvider;
use Chronhub\Storm\Contracts\Chronicler\ChroniclerConnection;
use Chronhub\Storm\Contracts\Chronicler\StreamEventLoaderConnection;
use function is_string;

abstract class Store implements ChroniclerConnection
{
    /**
     * Determine if we create a new stream, or we update one
     */
    protected bool $isCreation = false;

    public function __construct(protected readonly Connection $connection,
                                protected readonly StreamPersistence $streamPersistence,
                                protected readonly StreamEventLoaderConnection $eventLoader,
                                protected readonly EventStreamProvider $eventStreamProvider,
                                protected readonly StreamCategory $streamCategory,
                                protected readonly WriteLockStrategy $writeLock)
    {
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

        $builder = $this->connection->table($tableName);

        if ($this->writeLock instanceof MysqlWriteLock) {
            return $builder->lockForUpdate();
        }

        return $builder;
    }

    protected function forRead(StreamName $streamName): Builder
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        $indexName = $this->streamPersistence->indexName($tableName);

        //fixMe index failed at least for pgsql
        // comment out when resolved
//        if (is_string($indexName)) {
//            $raw = "`$tableName` USE INDEX($indexName)";
//
//            return $this->connection->query()->fromRaw($raw);
//        }

        return $this->connection->table($tableName);
    }

    protected function serializeStreamEvents(iterable $streamEvents): array
    {
        $events = [];

        foreach ($streamEvents as $streamEvent) {
            $events[] = $this->streamPersistence->serializeEvent($streamEvent);
        }

        return $events;
    }

    protected function upStreamTable(StreamName $streamName): void
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        try {
            $this->streamPersistence->up($tableName);
        } catch (QueryException $exception) {
            $this->connection->getSchemaBuilder()->drop($tableName);

            $this->eventStreamProvider->deleteStream($streamName->name);

            throw $exception;
        }
    }

    protected function createEventStream(StreamName $streamName): void
    {
        $tableName = $this->streamPersistence->tableName($streamName);

        // checkMe query exception should be raised on duplicate
        // todo assert in fn test
        $result = $this->eventStreamProvider->createStream(
            $streamName->name,
            $tableName,
            ($this->streamCategory)($streamName->name)
        );

        if (! $result) {
            throw new ConnectionQueryFailure("Unable to insert data for stream $streamName in event stream table");
        }
    }
}
