<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Generator;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Illuminate\Database\QueryException;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;
use function count;
use function array_map;

class EventStoreDatabase extends AbstractEventStoreDatabase
{
    /**
     * @throws QueryException
     */
    public function firstCommit(Stream $stream): void
    {
        $this->isCreation = true;

        $this->createEventStream($stream->name());

        $this->upStreamTable($stream->name());

        $this->isCreation = false;

        $this->amend($stream);
    }

    /**
     * @throws QueryException
     */
    public function amend(Stream $stream): void
    {
        $this->isCreation = false;

        $streamEvents = $this->serializeStreamEvents($stream->events());

        if (count($streamEvents) === 0) {
            return;
        }

        $tableName = $this->streamPersistence->tableName($stream->name());

        if (! $this->writeLock->acquireLock($tableName)) {
            throw ConnectionConcurrencyException::failedToAcquireLock();
        }

        try {
            $this->forWrite($stream->name())->insert($streamEvents);
        } finally {
            $this->writeLock->releaseLock($tableName);
        }
    }

    /**
     * @throws QueryException
     */
    public function delete(StreamName $streamName): void
    {
        try {
            $result = $this->eventStreamProvider->deleteStream($streamName->name);

            if (! $result) {
                throw StreamNotFound::withStreamName($streamName);
            }
        } catch (QueryException $exception) {
            // checkMe do we need 'not affected row' as it should be covered by false result above
            // does the laravel query builder set 00000 as code and raise exception
            if ('00000' !== $exception->getCode()) {
                throw $exception;
            }
        }

        try {
            $this->connection->getSchemaBuilder()->drop(
                $this->streamPersistence->tableName($streamName)
            );
        } catch (QueryException $exception) {
            //checkMe handle stream not found when dropping table which not exist

            if ('00000' !== $exception->getCode()) {
                throw $exception;
            }
        }
    }

    public function retrieveAll(StreamName $streamName, AggregateIdentity $aggregateId, string $direction = 'asc'): Generator
    {
        $query = $this->forRead($streamName);

        if ($this->streamPersistence->isAutoIncremented()) {
            $query = $query->where('aggregate_id', $aggregateId->toString());
        }

        $query = $query->orderBy('no', $direction);

        return $this->eventLoader->query($query, $streamName);
    }

    public function retrieveFiltered(StreamName $streamName, QueryFilter $queryFilter): Generator
    {
        $builder = $this->forRead($streamName);

        $queryFilter->apply()($builder);

        return $this->eventLoader->query($builder, $streamName);
    }

    public function filterStreamNames(StreamName ...$streamNames): array
    {
        return array_map(
            fn (string $streamName): StreamName => new StreamName($streamName),
            $this->eventStreamProvider->filterByStreams($streamNames)
        );
    }

    public function filterCategoryNames(string ...$categoryNames): array
    {
        return $this->eventStreamProvider->filterByCategories($categoryNames);
    }

    public function hasStream(StreamName $streamName): bool
    {
        return $this->eventStreamProvider->hasRealStreamName($streamName->name);
    }
}
