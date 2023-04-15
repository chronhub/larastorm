<?php

declare(strict_types=1);

namespace Chronhub\Larastorm\EventStore\Database;

use Chronhub\Larastorm\Exceptions\ConnectionConcurrencyException;
use Chronhub\Storm\Chronicler\Exceptions\StreamNotFound;
use Chronhub\Storm\Contracts\Aggregate\AggregateIdentity;
use Chronhub\Storm\Contracts\Chronicler\QueryFilter;
use Chronhub\Storm\Stream\Stream;
use Chronhub\Storm\Stream\StreamName;
use Generator;
use Illuminate\Database\QueryException;
use function array_map;
use function count;

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
            $deleted = $this->eventStreamProvider->deleteStream($streamName->name);

            if (! $deleted) {
                throw StreamNotFound::withStreamName($streamName);
            }
        } catch (QueryException $exception) {
            if ('00000' !== $exception->getCode()) {
                throw $exception;
            }
        }

        try {
            $this->connection->getSchemaBuilder()->drop(
                $this->streamPersistence->tableName($streamName)
            );
        } catch (QueryException $exception) {
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
            $this->eventStreamProvider->filterByAscendantStreams($streamNames)
        );
    }

    public function filterCategoryNames(string ...$categoryNames): array
    {
        return $this->eventStreamProvider->filterByAscendantCategories($categoryNames);
    }

    public function hasStream(StreamName $streamName): bool
    {
        return $this->eventStreamProvider->hasRealStreamName($streamName->name);
    }
}
